<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\DependencyInjection;

use AgentSkills\Bridge\Symfony\AI\Command\EvalSkillCommand;
use AgentSkills\Bridge\Symfony\AI\Command\ValidateSkillCommand;
use AgentSkills\Bridge\Symfony\AI\Evaluation\SymfonyLlmClient;
use AgentSkills\Bridge\Symfony\AI\Profiler\AgentSkillsDataCollector;
use AgentSkills\Bridge\Symfony\AI\SkillInputProcessor;
use AgentSkills\Bridge\Symfony\AI\SkillTool;
use AgentSkills\ChainSkillLoader;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregator;
use AgentSkills\Evaluation\EvalSuiteLoader;
use AgentSkills\Evaluation\Grader\LlmGrader;
use AgentSkills\Evaluation\Workspace\WorkspaceManager;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\GithubSkillLoader;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\SkillParser;
use AgentSkills\SkillParserInterface;
use AgentSkills\Validation\SkillValidator;
use AgentSkills\Validation\SkillValidatorInterface;
use Override;
use Symfony\AI\Agent\InputProcessorInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function count;
use function is_array;
use function is_string;
use function sprintf;

final class AgentSkillBundleExtension extends Extension
{
    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new AgentSkillsBundleConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new AgentSkillsBundleConfiguration(), $configs);

        if (!$config['skills']['enabled']) {
            return;
        }

        $container->setParameter('agent_skills.enabled', true);

        $container->registerForAutoconfiguration(SkillLoaderInterface::class)
            ->addTag('agent_skills.skill_loader');
        $container->registerForAutoconfiguration(SkillParserInterface::class)
            ->addTag('agent_skills.skill_parser');
        $container->registerForAutoconfiguration(SkillValidatorInterface::class)
            ->addTag('agent_skills.skill_validator');

        $container->setDefinition('agent_skills.parser', (new Definition(SkillParser::class))
            ->setLazy(true)
            ->setArguments([new Reference('filesystem')])
            ->addTag('proxy', ['interface' => SkillParserInterface::class]));

        $container->setDefinition('agent_skills.validator', (new Definition(SkillValidator::class))
            ->setLazy(true)
            ->addTag('proxy', ['interface' => SkillValidatorInterface::class]));

        /** @var array<string, array<string, mixed>> $agents */
        $agents = $config['skills']['agents'];
        $allEffectiveLoaderRefs = [];

        foreach ($agents as $agentName => $agentConfig) {
            $effectiveLoaderId = $this->registerAgentLoaders($container, $agentName, $agentConfig);
            $allEffectiveLoaderRefs[] = new Reference($effectiveLoaderId);

            $this->registerAgentInputProcessor($container, $agentName, $agentConfig, $effectiveLoaderId);
            $this->registerAgentTools($container, $agentName, $agentConfig, $effectiveLoaderId);
        }

        if (1 === count($allEffectiveLoaderRefs)) {
            $container->setAlias(SkillLoaderInterface::class, (string) $allEffectiveLoaderRefs[0]);
        } elseif (count($allEffectiveLoaderRefs) > 1) {
            $container->setDefinition('agent_skills.global_chain_loader', new Definition(ChainSkillLoader::class, [
                $allEffectiveLoaderRefs,
            ]));
            $container->setAlias(SkillLoaderInterface::class, 'agent_skills.global_chain_loader');
        }

        $this->registerEvaluationServices($config, $container, $agents);
        $this->registerProfiler($container);
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentLoaders(ContainerBuilder $container, string $agentName, array $agentConfig): string
    {
        $loader = is_string($agentConfig['loader'] ?? null) ? $agentConfig['loader'] : 'agent_skills.filesystem_loader';

        if ('agent_skills.filesystem_loader' !== $loader) {
            return $loader;
        }

        $fsLoaderId = sprintf('agent_skills.%s.filesystem_loader', $agentName);
        $directories = is_array($agentConfig['directories'] ?? null) ? $agentConfig['directories'] : [];

        $container->setDefinition($fsLoaderId, (new Definition(FilesystemSkillLoader::class))
            ->setArguments([
                $directories,
                new Reference('agent_skills.parser'),
                new Reference('agent_skills.validator'),
                new Reference('filesystem'),
            ]));

        $effectiveLoaderId = $fsLoaderId;

        $githubRepositories = is_array($agentConfig['github_repositories'] ?? null) ? $agentConfig['github_repositories'] : [];
        if ([] !== $githubRepositories) {
            $ghLoaderId = sprintf('agent_skills.%s.github_loader', $agentName);
            $container->setDefinition($ghLoaderId, (new Definition(GithubSkillLoader::class))
                ->setArguments([
                    $githubRepositories,
                    new Reference(HttpClientInterface::class),
                    new Reference('agent_skills.parser'),
                    new Reference('agent_skills.validator'),
                ]));

            $chainLoaderId = sprintf('agent_skills.%s.chain_loader', $agentName);
            $container->setDefinition($chainLoaderId, new Definition(ChainSkillLoader::class, [[
                new Reference($fsLoaderId),
                new Reference($ghLoaderId),
            ]]));

            $effectiveLoaderId = $chainLoaderId;
        }

        return $effectiveLoaderId;
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentInputProcessor(ContainerBuilder $container, string $agentName, array $agentConfig, string $effectiveLoaderId): void
    {
        $activeSkills = is_array($agentConfig['active_skills'] ?? null) ? $agentConfig['active_skills'] : [];
        $includeIndex = (bool) ($agentConfig['include_index'] ?? false);

        /** @var list<string> $activeSkillNames */
        $activeSkillNames = array_map(
            static fn (mixed $skill): string => is_array($skill) && is_string($skill['name'] ?? null) ? $skill['name'] : '',
            $activeSkills,
        );

        $inputProcessorId = sprintf('agent_skills.%s.input_processor', $agentName);

        $container->setDefinition($inputProcessorId, (new Definition(SkillInputProcessor::class))
            ->setLazy(true)
            ->setArguments([
                new Reference($effectiveLoaderId),
                $activeSkillNames,
                $includeIndex,
            ])
            ->addTag('proxy', ['interface' => InputProcessorInterface::class])
            ->addTag('ai.agent.input_processor', ['agent' => $agentName, 'priority' => -50]));
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentTools(ContainerBuilder $container, string $agentName, array $agentConfig, string $effectiveLoaderId): void
    {
        $activeSkills = is_array($agentConfig['active_skills'] ?? null) ? $agentConfig['active_skills'] : [];

        foreach ($activeSkills as $activeSkill) {
            if (!is_array($activeSkill) || !is_string($activeSkill['name'] ?? null)) {
                continue;
            }

            $skillName = $activeSkill['name'];
            $toolId = sprintf('agent_skills.tool.%s.%s', $agentName, $skillName);

            $container->setDefinition($toolId, (new Definition(SkillTool::class, [
                new Reference($effectiveLoaderId),
                $skillName,
            ]))->addTag('ai.agent.skill_as_tool'));

            $memoryFactoryId = sprintf('ai.toolbox.%s.memory_factory', $agentName);
            if ($container->hasDefinition($memoryFactoryId)) {
                $container->getDefinition($memoryFactoryId)->addMethodCall('addTool', [
                    new Reference($toolId),
                    sprintf('skill_%s', (new UnicodeString($skillName))->replace('-', '_')),
                    sprintf('Consult the "%s" skill for specialized knowledge and instructions. Pass an optional reference file path for detailed documentation.', $skillName),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, array<string, mixed>> $agents
     */
    private function registerEvaluationServices(array $config, ContainerBuilder $container, array $agents): void
    {
        $container->setDefinition('agent_skills.eval_suite_loader', new Definition(EvalSuiteLoader::class));

        $evaluation = is_array($config['evaluation'] ?? null) ? $config['evaluation'] : [];

        $workspace = is_string($evaluation['workspace'] ?? null) ? $evaluation['workspace'] : 'var/skill-evals';

        $container->setDefinition(
            'agent_skills.workspace_manager',
            (new Definition(WorkspaceManager::class))
            ->setArguments([$workspace]),
        );

        $container->setDefinition('agent_skills.benchmark_aggregator', new Definition(BenchmarkAggregator::class));

        $gradingModel = $evaluation['grading_model'] ?? null;
        $gradingPlatform = $evaluation['grading_platform'] ?? null;

        if (is_string($gradingModel) && is_string($gradingPlatform)) {
            $container->setDefinition('agent_skills.llm_client', (new Definition(SymfonyLlmClient::class))
                ->setArguments([
                    new Reference($gradingPlatform),
                    $gradingModel,
                ]));

            $container->setDefinition(
                'agent_skills.grader',
                (new Definition(LlmGrader::class))
                ->setArguments([new Reference('agent_skills.llm_client')]),
            );
        }

        if ([] !== $agents) {
            $container->setDefinition('agent_skills.command.validate_skills', (new Definition(ValidateSkillCommand::class))
                ->setArguments([
                    new Reference(SkillLoaderInterface::class),
                    new Reference('agent_skills.validator'),
                ])
                ->addTag('console.command'));

            $container->setDefinition('agent_skills.command.eval_skill', (new Definition(EvalSkillCommand::class))
                ->setArguments([
                    new Reference('agent_skills.eval_suite_loader'),
                    new Reference('agent_skills.workspace_manager'),
                    new Reference('agent_skills.benchmark_aggregator'),
                    new Reference('clock'),
                    new AutowireLocator('ai.agent'),
                    is_string($gradingModel) && is_string($gradingPlatform) ? new Reference('agent_skills.grader') : null,
                ])
                ->addTag('console.command'));
        }
    }

    private function registerProfiler(ContainerBuilder $container): void
    {
        $container->register(AgentSkillsDataCollector::class, AgentSkillsDataCollector::class)
            ->setArguments([
                new AutowireIterator('agent_skills.traceable_skill_loader'),
            ])
            ->setPublic(false)
            ->addTag('data_collector', [
                'template' => '@AgentSkills/data_collector.html.twig',
                'id' => 'agent_skill',
            ])
            ->addTag('container.preload', [
                'class' => AgentSkillsDataCollector::class,
            ])
        ;
    }
}
