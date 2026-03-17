<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\DependencyInjection;

use AgentSkills\Bridge\Symfony\AI\Command\EvalSkillCommand;
use AgentSkills\Bridge\Symfony\AI\Command\ValidateSkillCommand;
use AgentSkills\Bridge\Symfony\AI\Evaluation\SymfonyLlmClient;
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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\String\UnicodeString;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
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
        $agentSkillsBundleConfiguration = new AgentSkillsBundleConfiguration();

        $config = $this->processConfiguration($agentSkillsBundleConfiguration, $configs);

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

        if ('agent_skills.filesystem_loader' === $config['skills']['loader']) {
            $skillParserDefinition = (new Definition(SkillParser::class))
                ->setLazy(true)
                ->setArguments([
                    new Reference('filesystem'),
                ])
                ->addTag('proxy', ['interface' => SkillParserInterface::class]);

            $container->setDefinition('agent_skills.parser', $skillParserDefinition);

            $skillValidatorDefinition = (new Definition(SkillValidator::class))
                ->setLazy(true)
                ->addTag('proxy', ['interface' => SkillValidatorInterface::class]);

            $container->setDefinition('agent_skills.validator', $skillValidatorDefinition);

            $filesystemSkillLoaderDefinition = (new Definition(FilesystemSkillLoader::class))
                ->setArguments([
                    $config['skills']['directories'],
                    new Reference('agent_skills.parser'),
                    new Reference('agent_skills.validator'),
                    new Reference('filesystem'),
                ]);

            $container->setDefinition('agent_skills.filesystem_loader', $filesystemSkillLoaderDefinition);

            $githubRepositories = $config['skills']['github_repositories'] ?? [];
            if ([] !== $githubRepositories) {
                $githubSkillLoaderDefinition = (new Definition(GithubSkillLoader::class))
                    ->setArguments([
                        $githubRepositories,
                        new Reference(HttpClientInterface::class),
                        new Reference('agent_skills.parser'),
                        new Reference('agent_skills.validator'),
                    ]);

                $container->setDefinition('agent_skills.github_loader', $githubSkillLoaderDefinition);

                $chainSkillLoaderDefinition = (new Definition(ChainSkillLoader::class))
                    ->setArguments([[
                        new Reference('agent_skills.filesystem_loader'),
                        new Reference('agent_skills.github_loader'),
                    ]]);

                $container->setDefinition('agent_skills.chain_loader', $chainSkillLoaderDefinition);
            }
        }

        $effectiveLoaderId = $config['skills']['loader'];
        if ('agent_skills.filesystem_loader' === $effectiveLoaderId && [] !== ($config['skills']['github_repositories'] ?? [])) {
            $effectiveLoaderId = 'agent_skills.chain_loader';
        }

        $container->setAlias(SkillLoaderInterface::class, $effectiveLoaderId);

        $agentId = $config['skills']['agent'] ?? null;

        $skillInputProcessorDefinition = (new Definition(SkillInputProcessor::class))
            ->setLazy(true)
            ->setArguments([
                new Reference($effectiveLoaderId),
                array_map(
                    static fn (array $skill): string => $skill['name'],
                    $config['skills']['active_skills'],
                ),
                $config['skills']['include_index'],
            ])
            ->addTag('proxy', ['interface' => InputProcessorInterface::class])
            ->addTag('ai.agent.input_processor', ['agent' => $agentId, 'priority' => -50]);

        $container->setDefinition('agent_skills.input_processor', $skillInputProcessorDefinition);

        if (null !== $agentId) {
            foreach ($config['skills']['active_skills'] as $activeSkill) {
                $skillToolServiceIdentifier = sprintf('agent_skills.tool.%s.%s', $agentId, $activeSkill['name']);

                $skillToolDefinition = (new Definition(SkillTool::class, [
                    new Reference($effectiveLoaderId),
                    $activeSkill['name'],
                ]))->addTag('ai.agent.skill_as_tool');

                $container->setDefinition($skillToolServiceIdentifier, $skillToolDefinition);

                $memoryFactoryDefinition = $container->getDefinition('ai.toolbox.' . $agentId . '.memory_factory');
                $memoryFactoryDefinition->addMethodCall('addTool', [
                    new Reference($skillToolServiceIdentifier),
                    sprintf('skill_%s', (new UnicodeString($activeSkill['name']))->replace('-', '_')),
                    sprintf('Consult the "%s" skill for specialized knowledge and instructions. Pass an optional reference file path for detailed documentation.', $activeSkill['name']),
                ]);
            }
        }

        $this->registerEvaluationServices($config, $container, $effectiveLoaderId, $agentId);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerEvaluationServices(array $config, ContainerBuilder $container, string $effectiveLoaderId, ?string $agentId): void
    {
        $container->setDefinition('agent_skills.eval_suite_loader', new Definition(EvalSuiteLoader::class));

        $container->setDefinition(
            'agent_skills.workspace_manager',
            (new Definition(WorkspaceManager::class))
            ->setArguments([$config['evaluation']['workspace']]),
        );

        $container->setDefinition('agent_skills.benchmark_aggregator', new Definition(BenchmarkAggregator::class));

        $gradingModel = $config['evaluation']['grading_model'] ?? null;
        $gradingPlatform = $config['evaluation']['grading_platform'] ?? null;

        if (null !== $gradingModel && null !== $gradingPlatform) {
            $llmClientDefinition = (new Definition(SymfonyLlmClient::class))
                ->setArguments([
                    new Reference($gradingPlatform),
                    $gradingModel,
                ]);

            $container->setDefinition('agent_skills.llm_client', $llmClientDefinition);

            $container->setDefinition(
                'agent_skills.grader',
                (new Definition(LlmGrader::class))
                ->setArguments([new Reference('agent_skills.llm_client')]),
            );
        }

        $validateCommandDefinition = (new Definition(ValidateSkillCommand::class))
            ->setArguments([
                new Reference($effectiveLoaderId),
                new Reference('agent_skills.validator'),
            ])
            ->addTag('console.command');

        $container->setDefinition('agent_skills.command.validate_skills', $validateCommandDefinition);

        if (null !== $agentId) {
            $evalCommandDefinition = (new Definition(EvalSkillCommand::class))
                ->setArguments([
                    new Reference('agent_skills.eval_suite_loader'),
                    new Reference('agent_skills.workspace_manager'),
                    new Reference('agent_skills.benchmark_aggregator'),
                    new Reference('clock'),
                    new Reference('ai.agent_locator'),
                    null !== $gradingModel && null !== $gradingPlatform ? new Reference('agent_skills.grader') : null,
                ])
                ->addTag('console.command');

            $container->setDefinition('agent_skills.command.eval_skill', $evalCommandDefinition);
        }
    }
}
