<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI;

use AgentSkills\Bridge\Laravel\AI\Command\EvalSkillCommand;
use AgentSkills\Bridge\Laravel\AI\Command\ValidateSkillCommand;
use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelLlmClient;
use AgentSkills\Bridge\Laravel\AI\Middleware\SkillPromptMiddleware;
use AgentSkills\Bridge\Laravel\AI\Tool\ExecuteSkillScriptTool;
use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillsTool;
use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillTool;
use AgentSkills\ChainSkillLoader;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregator;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregatorInterface;
use AgentSkills\Evaluation\EvalSuiteLoader;
use AgentSkills\Evaluation\EvalSuiteLoaderInterface;
use AgentSkills\Evaluation\Grader\GraderInterface;
use AgentSkills\Evaluation\Grader\LlmGrader;
use AgentSkills\Evaluation\Workspace\WorkspaceManager;
use AgentSkills\Evaluation\Workspace\WorkspaceManagerInterface;
use AgentSkills\Exception\RuntimeException;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\GithubSkillLoader;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\SkillParser;
use AgentSkills\SkillParserInterface;
use AgentSkills\Validation\SkillValidator;
use AgentSkills\Validation\SkillValidatorInterface;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;
use Override;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function count;
use function dirname;
use function is_array;
use function is_string;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentSkillsServiceProvider extends ServiceProvider
{
    /** @var Application */
    protected $app;

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            dirname(__DIR__, 4) . '/config/agent-skills.php',
            'agent-skills',
        );

        if (!$this->config()->get('agent-skills.skills.enabled', false)) {
            return;
        }

        $this->registerCoreServices();
        $this->registerAgents();
        $this->registerEvaluationServices();
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 4) . '/config/agent-skills.php' => $this->app->configPath('agent-skills.php'),
        ], 'agent-skills-config');

        if (!$this->app->runningInConsole()) {
            return;
        }

        if (!$this->config()->get('agent-skills.skills.enabled', false)) {
            return;
        }

        $commands = [ValidateSkillCommand::class];

        /** @var array<string, mixed> $agents */
        $agents = $this->config()->get('agent-skills.skills.agents', []);

        if ([] !== $agents) {
            $commands[] = EvalSkillCommand::class;
        }

        $this->commands($commands);
    }

    private function registerCoreServices(): void
    {
        $this->app->singleton(SkillParserInterface::class, static fn (): SkillParser => new SkillParser());

        $this->app->singleton(SkillValidatorInterface::class, static fn (): SkillValidator => new SkillValidator());
    }

    private function registerAgents(): void
    {
        /** @var array<string, mixed> $agents */
        $agents = $this->config()->get('agent-skills.skills.agents', []);
        $allLoaderKeys = [];

        foreach ($agents as $agentName => $agentConfig) {
            if (!is_array($agentConfig)) {
                continue;
            }

            /** @var array<string, mixed> $agentConfig */
            $effectiveLoaderKey = $this->registerAgentLoaders((string) $agentName, $agentConfig);
            $allLoaderKeys[] = $effectiveLoaderKey;

            $this->registerAgentMiddleware((string) $agentName, $agentConfig, $effectiveLoaderKey);
            $this->registerAgentTools((string) $agentName, $agentConfig, $effectiveLoaderKey);
        }

        if (1 === count($allLoaderKeys)) {
            $this->app->alias($allLoaderKeys[0], SkillLoaderInterface::class);
        } elseif (count($allLoaderKeys) > 1) {
            $this->app->singleton(SkillLoaderInterface::class, static fn ($app): ChainSkillLoader => new ChainSkillLoader(
                array_map(static fn (string $key): SkillLoaderInterface => $app->make($key), $allLoaderKeys),
            ));
        }
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentLoaders(string $agentName, array $agentConfig): string
    {
        /** @var array<int, string> $directories */
        $directories = is_array($agentConfig['directories'] ?? null) ? $agentConfig['directories'] : [];

        $fsLoaderId = sprintf('agent_skills.%s.filesystem_loader', $agentName);

        $this->app->singleton($fsLoaderId, static fn ($app): FilesystemSkillLoader => new FilesystemSkillLoader(
            $directories,
            $app->make(SkillParserInterface::class),
            $app->make(SkillValidatorInterface::class),
        ));

        $effectiveLoaderKey = $fsLoaderId;

        /** @var array<int, array{repository: string, path?: string, branch?: string, token?: string|null}> $githubRepositories */
        $githubRepositories = is_array($agentConfig['github_repositories'] ?? null) ? $agentConfig['github_repositories'] : [];

        if ([] !== $githubRepositories) {
            $ghLoaderId = sprintf('agent_skills.%s.github_loader', $agentName);

            $this->app->singleton($ghLoaderId, static fn ($app): GithubSkillLoader => new GithubSkillLoader(
                $githubRepositories,
                $app->make(HttpClientInterface::class),
                $app->make(SkillParserInterface::class),
                $app->make(SkillValidatorInterface::class),
            ));

            $chainLoaderId = sprintf('agent_skills.%s.chain_loader', $agentName);

            $this->app->singleton($chainLoaderId, static function ($app) use ($fsLoaderId, $ghLoaderId): ChainSkillLoader {
                return new ChainSkillLoader([
                    $app->make($fsLoaderId),
                    $app->make($ghLoaderId),
                ]);
            });

            $effectiveLoaderKey = $chainLoaderId;
        }

        return $effectiveLoaderKey;
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentMiddleware(string $agentName, array $agentConfig, string $effectiveLoaderKey): void
    {
        /** @var array<int, string> $activeSkills */
        $activeSkills = is_array($agentConfig['active_skills'] ?? null) ? $agentConfig['active_skills'] : [];
        $includeIndex = (bool) ($agentConfig['include_index'] ?? false);

        $middlewareId = sprintf('agent_skills.%s.middleware', $agentName);

        $this->app->singleton($middlewareId, static function ($app) use ($effectiveLoaderKey, $activeSkills, $includeIndex): SkillPromptMiddleware {
            return new SkillPromptMiddleware(
                $app->make($effectiveLoaderKey),
                $activeSkills,
                $includeIndex,
            );
        });
    }

    /**
     * @param array<string, mixed> $agentConfig
     */
    private function registerAgentTools(string $agentName, array $agentConfig, string $effectiveLoaderKey): void
    {
        /** @var mixed[] $activeSkills */
        $activeSkills = is_array($agentConfig['active_skills'] ?? null) ? $agentConfig['active_skills'] : [];

        $getSkillsToolId = sprintf('agent_skills.tool.%s.get_skills', $agentName);
        $this->app->singleton($getSkillsToolId, static function ($app) use ($effectiveLoaderKey): GetSkillsTool {
            return new GetSkillsTool($app->make($effectiveLoaderKey));
        });

        foreach ($activeSkills as $skillName) {
            if (!is_string($skillName) || '' === $skillName) {
                continue;
            }

            $getSkillToolId = sprintf('agent_skills.tool.%s.%s', $agentName, $skillName);
            $this->app->singleton($getSkillToolId, static function ($app) use ($effectiveLoaderKey, $skillName): GetSkillTool {
                return new GetSkillTool($app->make($effectiveLoaderKey), $skillName);
            });

            $executeScriptToolId = sprintf('agent_skills.tool.%s.execute_script.%s', $agentName, $skillName);
            $this->app->singleton($executeScriptToolId, static function ($app) use ($effectiveLoaderKey, $skillName): ExecuteSkillScriptTool {
                return new ExecuteSkillScriptTool($app->make($effectiveLoaderKey), $skillName);
            });
        }
    }

    private function config(): Repository
    {
        $config = $this->app['config'];

        if (!$config instanceof Repository) {
            throw new RuntimeException('Config repository not available.');
        }

        return $config;
    }

    private function registerEvaluationServices(): void
    {
        $this->app->singleton(EvalSuiteLoaderInterface::class, static fn (): EvalSuiteLoader => new EvalSuiteLoader());

        /** @var string $workspace */
        $workspace = $this->config()->get('agent-skills.evaluation.workspace', 'storage/app/skill-evals');

        $this->app->singleton(WorkspaceManagerInterface::class, static fn (): WorkspaceManager => new WorkspaceManager($workspace));

        $this->app->singleton(BenchmarkAggregatorInterface::class, static fn (): BenchmarkAggregator => new BenchmarkAggregator());

        $gradingModel = $this->config()->get('agent-skills.evaluation.grading_model');
        $gradingProvider = $this->config()->get('agent-skills.evaluation.grading_provider');

        if (is_string($gradingModel) && is_string($gradingProvider)) {
            $this->app->singleton(GraderInterface::class, static function ($app) use ($gradingModel, $gradingProvider): LlmGrader {
                $llmClient = new LaravelLlmClient(
                    $app->make(AiManager::class),
                    $gradingProvider,
                    $gradingModel,
                );

                return new LlmGrader($llmClient);
            });
        }
    }
}
