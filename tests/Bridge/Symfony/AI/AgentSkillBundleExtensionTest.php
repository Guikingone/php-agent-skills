<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Symfony\AI;

use AgentSkills\Bridge\Symfony\AI\Command\EvalSkillCommand;
use AgentSkills\Bridge\Symfony\AI\Command\ValidateSkillCommand;
use AgentSkills\Bridge\Symfony\AI\DependencyInjection\AgentSkillBundleExtension;
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
use AgentSkills\Validation\SkillValidator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function array_keys;

final class AgentSkillBundleExtensionTest extends TestCase
{
    public function testLoadDoesNothingWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => ['enabled' => false]]], $container);

        $this->assertFalse($container->hasParameter('agent_skills.enabled'));
        $this->assertFalse($container->hasDefinition('agent_skills.filesystem_loader'));
        $this->assertFalse($container->hasDefinition('agent_skills.input_processor'));
    }

    public function testLoadRegistersFilesystemLoaderAndInputProcessor(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertTrue($container->hasParameter('agent_skills.enabled'));
        $this->assertTrue($container->hasDefinition('agent_skills.parser'));
        $this->assertTrue($container->hasDefinition('agent_skills.validator'));
        $this->assertTrue($container->hasDefinition('agent_skills.filesystem_loader'));
        $this->assertTrue($container->hasDefinition('agent_skills.input_processor'));
        $this->assertTrue($container->hasAlias(SkillLoaderInterface::class));

        $this->assertSame(SkillParser::class, $container->getDefinition('agent_skills.parser')->getClass());
        $this->assertSame(SkillValidator::class, $container->getDefinition('agent_skills.validator')->getClass());
        $this->assertSame(FilesystemSkillLoader::class, $container->getDefinition('agent_skills.filesystem_loader')->getClass());
        $this->assertSame(SkillInputProcessor::class, $container->getDefinition('agent_skills.input_processor')->getClass());
    }

    public function testLoadRegistersGithubLoaderAndChainLoader(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'github_repositories' => [
                ['repository' => 'my-org/skills', 'path' => '', 'branch' => 'main', 'token' => null],
            ],
            'active_skills' => [],
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.github_loader'));
        $this->assertTrue($container->hasDefinition('agent_skills.chain_loader'));

        $this->assertSame(GithubSkillLoader::class, $container->getDefinition('agent_skills.github_loader')->getClass());
        $this->assertSame(ChainSkillLoader::class, $container->getDefinition('agent_skills.chain_loader')->getClass());

        // The alias should point to the chain loader when github repos are configured
        $this->assertSame('agent_skills.chain_loader', (string) $container->getAlias(SkillLoaderInterface::class));
    }

    public function testLoadRegistersSkillToolsWhenAgentIsSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        // Pre-register the memory factory definition that the extension expects
        $container->setDefinition('ai.toolbox.my_agent.memory_factory', new Definition(stdClass::class));

        $extension->load([['skills' => [
            'enabled' => true,
            'agent' => 'my_agent',
            'directories' => ['/tmp/skills'],
            'active_skills' => [
                ['name' => 'code-review'],
                ['name' => 'twig-component'],
            ],
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.tool.my_agent.code-review'));
        $this->assertTrue($container->hasDefinition('agent_skills.tool.my_agent.twig-component'));

        $toolDefinition = $container->getDefinition('agent_skills.tool.my_agent.code-review');
        $this->assertSame(SkillTool::class, $toolDefinition->getClass());
        $this->assertTrue($toolDefinition->hasTag('ai.agent.skill_as_tool'));
    }

    public function testLoadDoesNotRegisterToolsWhenAgentIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [
                ['name' => 'code-review'],
            ],
        ]]], $container);

        $this->assertFalse($container->hasDefinition('agent_skills.tool..code-review'));

        // No SkillTool definitions should exist
        foreach (array_keys($container->getDefinitions()) as $id) {
            $this->assertStringNotContainsString('agent_skills.tool.', $id);
        }
    }

    public function testLoadRegistersEvaluationServices(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.eval_suite_loader'));
        $this->assertTrue($container->hasDefinition('agent_skills.workspace_manager'));
        $this->assertTrue($container->hasDefinition('agent_skills.benchmark_aggregator'));

        $this->assertSame(EvalSuiteLoader::class, $container->getDefinition('agent_skills.eval_suite_loader')->getClass());
        $this->assertSame(WorkspaceManager::class, $container->getDefinition('agent_skills.workspace_manager')->getClass());
        $this->assertSame(BenchmarkAggregator::class, $container->getDefinition('agent_skills.benchmark_aggregator')->getClass());
    }

    public function testLoadRegistersGraderWhenGradingConfigured(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ], 'evaluation' => [
            'grading_model' => 'gpt-4o-mini',
            'grading_platform' => 'ai.platform.openai',
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.llm_client'));
        $this->assertTrue($container->hasDefinition('agent_skills.grader'));
        $this->assertSame(LlmGrader::class, $container->getDefinition('agent_skills.grader')->getClass());
    }

    public function testLoadDoesNotRegisterGraderWhenGradingNotConfigured(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertFalse($container->hasDefinition('agent_skills.llm_client'));
        $this->assertFalse($container->hasDefinition('agent_skills.grader'));
    }

    public function testLoadRegistersValidateCommand(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.command.validate_skills'));
        $this->assertSame(ValidateSkillCommand::class, $container->getDefinition('agent_skills.command.validate_skills')->getClass());
        $this->assertTrue($container->getDefinition('agent_skills.command.validate_skills')->hasTag('console.command'));
    }

    public function testLoadRegistersEvalCommandWhenAgentIsSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $container->setDefinition('ai.toolbox.my_agent.memory_factory', new Definition(stdClass::class));

        $extension->load([['skills' => [
            'enabled' => true,
            'agent' => 'my_agent',
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertTrue($container->hasDefinition('agent_skills.command.eval_skill'));
        $this->assertSame(EvalSkillCommand::class, $container->getDefinition('agent_skills.command.eval_skill')->getClass());
        $this->assertTrue($container->getDefinition('agent_skills.command.eval_skill')->hasTag('console.command'));
    }

    public function testLoadDoesNotRegisterEvalCommandWhenAgentIsNotSet(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => [],
        ]]], $container);

        $this->assertFalse($container->hasDefinition('agent_skills.command.eval_skill'));
    }

    public function testConfigurationNormalizesActiveSkillStrings(): void
    {
        $container = new ContainerBuilder();
        $extension = new AgentSkillBundleExtension();

        // Pass active_skills as plain strings — the configuration normalizer should convert them
        $extension->load([['skills' => [
            'enabled' => true,
            'directories' => ['/tmp/skills'],
            'active_skills' => ['my-skill', 'other-skill'],
        ]]], $container);

        // The input processor should have been registered with the normalized skill names
        $this->assertTrue($container->hasDefinition('agent_skills.input_processor'));

        $inputProcessorArgs = $container->getDefinition('agent_skills.input_processor')->getArguments();
        $this->assertSame(['my-skill', 'other-skill'], $inputProcessorArgs[1]);
    }
}
