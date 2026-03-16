<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI;

use AgentSkills\Bridge\Laravel\AI\AgentSkillsServiceProvider;
use AgentSkills\Bridge\Laravel\AI\Middleware\SkillPromptMiddleware;
use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillsTool;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregatorInterface;
use AgentSkills\Evaluation\EvalSuiteLoaderInterface;
use AgentSkills\Evaluation\Grader\GraderInterface;
use AgentSkills\Evaluation\Workspace\WorkspaceManagerInterface;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\SkillParserInterface;
use AgentSkills\Validation\SkillValidatorInterface;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class AgentSkillsServiceProviderTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        Container::setInstance($this->container);

        $this->container->instance('config', new Repository());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testDoesNothingWhenDisabled()
    {
        $this->container['config']->set('agent-skills.skills.enabled', false);

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertFalse($this->container->bound(SkillParserInterface::class));
        $this->assertFalse($this->container->bound(SkillValidatorInterface::class));
    }

    public function testRegistersCoreSingletons()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(SkillParserInterface::class));
        $this->assertTrue($this->container->bound(SkillValidatorInterface::class));
    }

    public function testRegistersFilesystemLoader()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound('agent_skills.filesystem_loader'));
    }

    public function testRegistersSkillLoaderAlias()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(SkillLoaderInterface::class) || $this->container->isAlias(SkillLoaderInterface::class));
    }

    public function testRegistersMiddleware()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(SkillPromptMiddleware::class));
    }

    public function testRegistersGetSkillsTool()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(GetSkillsTool::class));
    }

    public function testRegistersPerSkillTools()
    {
        $this->configureEnabled(['active_skills' => ['code-review', 'testing']]);

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound('agent_skills.tool.get_skill.code-review'));
        $this->assertTrue($this->container->bound('agent_skills.tool.execute_script.code-review'));
        $this->assertTrue($this->container->bound('agent_skills.tool.get_skill.testing'));
        $this->assertTrue($this->container->bound('agent_skills.tool.execute_script.testing'));
    }

    public function testRegistersEvaluationServices()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(EvalSuiteLoaderInterface::class));
        $this->assertTrue($this->container->bound(WorkspaceManagerInterface::class));
        $this->assertTrue($this->container->bound(BenchmarkAggregatorInterface::class));
    }

    public function testDoesNotRegisterGraderWhenNotConfigured()
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertFalse($this->container->bound(GraderInterface::class));
    }

    public function testRegistersGraderWhenConfigured()
    {
        $this->configureEnabled([], ['grading_model' => 'gpt-4o-mini', 'grading_provider' => 'openai']);

        $provider = new AgentSkillsServiceProvider($this->container);
        $provider->register();

        $this->assertTrue($this->container->bound(GraderInterface::class));
    }

    /**
     * @param array<string, mixed> $skillOverrides
     * @param array<string, mixed> $evalOverrides
     */
    private function configureEnabled(array $skillOverrides = [], array $evalOverrides = []): void
    {
        $skills = [
            'enabled' => true,
            'agent' => null,
            'directories' => [sys_get_temp_dir()],
            'github_repositories' => [],
            'active_skills' => [],
            'include_index' => false,
            ...$skillOverrides,
        ];

        $evaluation = [
            'workspace' => sys_get_temp_dir() . '/skill-evals',
            'grading_model' => null,
            'grading_provider' => null,
            ...$evalOverrides,
        ];

        $this->container['config']->set('agent-skills.skills', $skills);
        $this->container['config']->set('agent-skills.evaluation', $evaluation);
    }
}
