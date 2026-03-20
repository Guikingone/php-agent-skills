<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI;

use AgentSkills\Bridge\Laravel\AI\AgentSkillsServiceProvider;
use AgentSkills\ChainSkillLoader;
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
    private Repository $config;

    protected function setUp(): void
    {
        $this->app = new Application(sys_get_temp_dir());
        $this->config = new Repository();
        $this->app->instance('config', $this->config);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function testDoesNothingWhenDisabled(): void
    {
        $this->config->set('agent-skills.skills.enabled', false);

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

    public function testRegistersPerAgentFilesystemLoader(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.default.filesystem_loader'));
    }

    public function testRegistersSkillLoaderAlias(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(SkillLoaderInterface::class) || $this->app->isAlias(SkillLoaderInterface::class));
    }

    public function testRegistersPerAgentMiddleware(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.default.middleware'));
    }

    public function testRegistersPerAgentGetSkillsTool(): void
    {
        $this->configureEnabled();

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.tool.default.get_skills'));
    }

    public function testRegistersPerAgentPerSkillTools(): void
    {
        $this->configureEnabled(['active_skills' => ['code-review', 'testing']]);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.tool.default.code-review'));
        $this->assertTrue($this->app->bound('agent_skills.tool.default.execute_script.code-review'));
        $this->assertTrue($this->app->bound('agent_skills.tool.default.testing'));
        $this->assertTrue($this->app->bound('agent_skills.tool.default.execute_script.testing'));
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

    public function testMultipleAgentsEachGetOwnLoader(): void
    {
        $this->config->set('agent-skills.skills', [
            'enabled' => true,
            'agents' => [
                'agent_one' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => [],
                    'include_index' => false,
                ],
                'agent_two' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => [],
                    'include_index' => false,
                ],
            ],
        ]);
        $this->config->set('agent-skills.evaluation', [
            'workspace' => sys_get_temp_dir() . '/skill-evals',
            'grading_model' => null,
            'grading_provider' => null,
        ]);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.agent_one.filesystem_loader'));
        $this->assertTrue($this->app->bound('agent_skills.agent_two.filesystem_loader'));
        $this->assertTrue($this->app->bound('agent_skills.agent_one.middleware'));
        $this->assertTrue($this->app->bound('agent_skills.agent_two.middleware'));
        $this->assertTrue($this->app->bound('agent_skills.tool.agent_one.get_skills'));
        $this->assertTrue($this->app->bound('agent_skills.tool.agent_two.get_skills'));
    }

    public function testMultipleAgentsGetGlobalChainLoader(): void
    {
        $this->config->set('agent-skills.skills', [
            'enabled' => true,
            'agents' => [
                'agent_one' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => [],
                    'include_index' => false,
                ],
                'agent_two' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => [],
                    'include_index' => false,
                ],
            ],
        ]);
        $this->config->set('agent-skills.evaluation', [
            'workspace' => sys_get_temp_dir() . '/skill-evals',
            'grading_model' => null,
            'grading_provider' => null,
        ]);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(SkillLoaderInterface::class));

        $loader = $this->app->make(SkillLoaderInterface::class);
        $this->assertInstanceOf(ChainSkillLoader::class, $loader);
    }

    public function testPerAgentToolsWithMultipleAgents(): void
    {
        $this->config->set('agent-skills.skills', [
            'enabled' => true,
            'agents' => [
                'reviewer' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => ['code-review'],
                    'include_index' => false,
                ],
                'assistant' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => ['twig-component'],
                    'include_index' => true,
                ],
            ],
        ]);
        $this->config->set('agent-skills.evaluation', [
            'workspace' => sys_get_temp_dir() . '/skill-evals',
            'grading_model' => null,
            'grading_provider' => null,
        ]);

        $provider = new AgentSkillsServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('agent_skills.tool.reviewer.code-review'));
        $this->assertTrue($this->app->bound('agent_skills.tool.reviewer.execute_script.code-review'));
        $this->assertFalse($this->app->bound('agent_skills.tool.reviewer.twig-component'));

        $this->assertTrue($this->app->bound('agent_skills.tool.assistant.twig-component'));
        $this->assertTrue($this->app->bound('agent_skills.tool.assistant.execute_script.twig-component'));
        $this->assertFalse($this->app->bound('agent_skills.tool.assistant.code-review'));
    }

    /**
     * @param array<string, mixed> $agentOverrides
     * @param array<string, mixed> $evalOverrides
     */
    private function configureEnabled(array $agentOverrides = [], array $evalOverrides = []): void
    {
        $skills = [
            'enabled' => true,
            'agents' => [
                'default' => [
                    'directories' => [sys_get_temp_dir()],
                    'github_repositories' => [],
                    'active_skills' => [],
                    'include_index' => false,
                    ...$agentOverrides,
                ],
            ],
        ];

        $evaluation = [
            'workspace' => sys_get_temp_dir() . '/skill-evals',
            'grading_model' => null,
            'grading_provider' => null,
            ...$evalOverrides,
        ];

        $this->config->set('agent-skills.skills', $skills);
        $this->config->set('agent-skills.evaluation', $evaluation);
    }
}
