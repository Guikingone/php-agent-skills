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
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

final class AgentSkillsServiceProviderTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application(sys_get_temp_dir());
        $this->app->instance('config', new Repository());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $this->app['config']->set('agent-skills.skills.enabled', false);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertFalse($this->app->bound(SkillParserInterface::class));
        $this->assertFalse($this->app->bound(SkillValidatorInterface::class));
    }

    public function testRegistersCoreSingletons(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(SkillParserInterface::class));
        $this->assertTrue($this->app->bound(SkillValidatorInterface::class));
    }

    public function testRegistersFilesystemLoader(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.filesystem_loader'));
    }

    public function testRegistersSkillLoaderAlias(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(SkillLoaderInterface::class) || $this->app->isAlias(SkillLoaderInterface::class));
    }

    public function testRegistersMiddleware(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(SkillPromptMiddleware::class));
    }

    public function testRegistersGetSkillsTool(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(GetSkillsTool::class));
    }

    public function testRegistersPerSkillTools(): void
    {
        $this->configureEnabled(['active_skills' => ['code-review', 'testing']]);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.tool.get_skill.code-review'));
        $this->assertTrue($this->app->bound('agent_skills.tool.execute_script.code-review'));
        $this->assertTrue($this->app->bound('agent_skills.tool.get_skill.testing'));
        $this->assertTrue($this->app->bound('agent_skills.tool.execute_script.testing'));
    }

    public function testRegistersEvaluationServices(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(EvalSuiteLoaderInterface::class));
        $this->assertTrue($this->app->bound(WorkspaceManagerInterface::class));
        $this->assertTrue($this->app->bound(BenchmarkAggregatorInterface::class));
    }

    public function testDoesNotRegisterGraderWhenNotConfigured(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertFalse($this->app->bound(GraderInterface::class));
    }

    public function testRegistersGraderWhenConfigured(): void
    {
        $this->configureEnabled([], ['grading_model' => 'gpt-4o-mini', 'grading_provider' => 'openai']);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(GraderInterface::class));
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

        $this->app['config']->set('agent-skills.skills', $skills);
        $this->app['config']->set('agent-skills.evaluation', $evaluation);
    }
}
