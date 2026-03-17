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

use function dirname;
use function is_string;

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
        $this->registerSkillLoaders();
        $this->registerMiddleware();
        $this->registerTools();
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

        if (null !== $this->config()->get('agent-skills.skills.agent')) {
            $commands[] = EvalSkillCommand::class;
        }

        $this->commands($commands);
    }

    private function registerCoreServices(): void
    {
        $this->app->singleton(SkillParserInterface::class, static fn (): SkillParser => new SkillParser());

        $this->app->singleton(SkillValidatorInterface::class, static fn (): SkillValidator => new SkillValidator());
    }

    private function registerSkillLoaders(): void
    {
        /** @var array<int, string> $directories */
        $directories = $this->config()->get('agent-skills.skills.directories', []);

        $this->app->singleton('agent_skills.filesystem_loader', static fn ($app): FilesystemSkillLoader => new FilesystemSkillLoader(
            $directories,
            $app->make(SkillParserInterface::class),
            $app->make(SkillValidatorInterface::class),
        ));

        /** @var array<int, array{repository: string, path?: string, branch?: string, token?: string|null}> $githubRepositories */
        $githubRepositories = $this->config()->get('agent-skills.skills.github_repositories', []);

        if ([] !== $githubRepositories) {
            $this->app->singleton('agent_skills.github_loader', static fn ($app): GithubSkillLoader => new GithubSkillLoader(
                $githubRepositories,
                $app->make(HttpClientInterface::class),
                $app->make(SkillParserInterface::class),
                $app->make(SkillValidatorInterface::class),
            ));

            $this->app->singleton(SkillLoaderInterface::class, static fn ($app): ChainSkillLoader => new ChainSkillLoader([
                $app->make('agent_skills.filesystem_loader'),
                $app->make('agent_skills.github_loader'),
            ]));
        } else {
            $this->app->alias('agent_skills.filesystem_loader', SkillLoaderInterface::class);
        }
    }

    private function registerMiddleware(): void
    {
        /** @var array<int, string> $activeSkills */
        $activeSkills = $this->config()->get('agent-skills.skills.active_skills', []);
        $includeIndex = (bool) $this->config()->get('agent-skills.skills.include_index', false);

        $this->app->singleton(SkillPromptMiddleware::class, static fn ($app): SkillPromptMiddleware => new SkillPromptMiddleware(
            $app->make(SkillLoaderInterface::class),
            $activeSkills,
            $includeIndex,
        ));
    }

    private function registerTools(): void
    {
        /** @var array<int, string> $activeSkills */
        $activeSkills = $this->config()->get('agent-skills.skills.active_skills', []);

        $this->app->singleton(GetSkillsTool::class, static fn ($app): GetSkillsTool => new GetSkillsTool($app->make(SkillLoaderInterface::class)));

        foreach ($activeSkills as $skillName) {
            $this->app->singleton('agent_skills.tool.get_skill.' . $skillName, static fn ($app): GetSkillTool => new GetSkillTool($app->make(SkillLoaderInterface::class), $skillName));

            $this->app->singleton('agent_skills.tool.execute_script.' . $skillName, static fn ($app): ExecuteSkillScriptTool => new ExecuteSkillScriptTool($app->make(SkillLoaderInterface::class), $skillName));
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
