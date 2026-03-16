<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Command;

use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelAgentExecutor;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregatorInterface;
use AgentSkills\Evaluation\EvalRunResult;
use AgentSkills\Evaluation\EvalSuite;
use AgentSkills\Evaluation\EvalSuiteLoaderInterface;
use AgentSkills\Evaluation\Grader\GraderInterface;
use AgentSkills\Evaluation\Runner\EvalRunner;
use AgentSkills\Evaluation\Workspace\WorkspaceManagerInterface;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\Container;
use Laravel\Ai\Contracts\Agent;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

use function count;
use function mb_substr;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalSkillCommand extends Command
{
    /** @var string */
    protected $signature = 'ai:agent:eval-skill
        {skill-directory : Path to the skill directory}
        {workspace? : Path to the workspace directory}
        {--iteration=1 : Iteration number}
        {--agent= : Agent class name for with-skill runs}
        {--baseline-agent= : Agent class name for without-skill (baseline) runs}
        {--skip-grading : Skip LLM grading}';

    /** @var string */
    protected $description = 'Evaluate an Agent Skill using its evals.json test suite';

    public function __construct(
        private readonly EvalSuiteLoaderInterface $evalSuiteLoader,
        private readonly WorkspaceManagerInterface $workspaceManager,
        private readonly BenchmarkAggregatorInterface $benchmarkAggregator,
        private readonly ClockInterface $clock,
        private readonly Container $container,
        private readonly ?GraderInterface $grader = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $skillDirectory = $this->argument('skill-directory');
        $iteration = (int) $this->option('iteration');
        $skipGrading = (bool) $this->option('skip-grading');

        try {
            $suite = $this->evalSuiteLoader->load($skillDirectory);
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to load eval suite: %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf('Evaluating skill: %s', $suite->getSkillName()));
        $this->line(sprintf('Found %d eval case(s)', count($suite->getEvals())));

        $agentName = $this->option('agent');
        $baselineAgentName = $this->option('baseline-agent');

        if (null === $agentName) {
            $this->error('The --agent option is required.');

            return self::FAILURE;
        }

        $this->workspaceManager->initializeIteration($iteration);

        $withSkillResults = $this->runEvals($suite, $agentName, $iteration, 'with_skill', $skipGrading);

        $withoutSkillResults = [];
        if (null !== $baselineAgentName) {
            $withoutSkillResults = $this->runEvals($suite, $baselineAgentName, $iteration, 'without_skill', $skipGrading);
        }

        if ([] !== $withSkillResults && [] !== $withoutSkillResults) {
            $benchmark = $this->benchmarkAggregator->aggregate($withSkillResults, $withoutSkillResults);
            $this->workspaceManager->saveBenchmarkResult($iteration, $benchmark);

            $this->newLine();
            $this->info('Benchmark Results');

            $delta = $benchmark->getDelta();
            $this->table(
                ['Metric', 'With Skill', 'Without Skill', 'Delta'],
                [
                    ['Pass Rate', sprintf('%.2f', $benchmark->getWithSkillPassRate()->getMean()), sprintf('%.2f', $benchmark->getWithoutSkillPassRate()->getMean()), sprintf('%+.2f', $delta['pass_rate'])],
                    ['Time (s)', sprintf('%.1f', $benchmark->getWithSkillTime()->getMean() / 1000), sprintf('%.1f', $benchmark->getWithoutSkillTime()->getMean() / 1000), sprintf('%+.1f', $delta['time_seconds'])],
                    ['Tokens', sprintf('%.0f', $benchmark->getWithSkillTokens()->getMean()), sprintf('%.0f', $benchmark->getWithoutSkillTokens()->getMean()), sprintf('%+.0f', $delta['tokens'])],
                ],
            );
        }

        $this->info(sprintf('Evaluation complete. Results saved to iteration-%d.', $iteration));

        return self::SUCCESS;
    }

    /**
     * @return EvalRunResult[]
     */
    private function runEvals(EvalSuite $suite, string $agentName, int $iteration, string $configuration, bool $skipGrading): array
    {
        /** @var Agent $agent */
        $agent = $this->container->make($agentName);
        $runner = new EvalRunner(new LaravelAgentExecutor($agent), $this->clock);

        $this->newLine();
        $this->info(sprintf('Running %s evals with agent "%s"', $configuration, $agentName));

        $results = [];
        foreach ($suite->getEvals() as $evalCase) {
            $this->output->write(sprintf('  Eval #%d: %s ... ', $evalCase->getId(), mb_substr($evalCase->getPrompt(), 0, 50)));

            $runResult = $runner->run($evalCase);

            $evalDir = $this->workspaceManager->getEvalDirectory($iteration, (string) $evalCase->getId(), $configuration);
            $this->workspaceManager->saveOutput($evalDir, $runResult->getOutput());
            $this->workspaceManager->saveTimingResult($evalDir, $runResult->getTiming());

            if (!$skipGrading && null !== $this->grader && [] !== $evalCase->getAssertions()) {
                $grading = $this->grader->grade($runResult->getOutput(), $evalCase->getAssertions(), $evalCase->getExpectedOutput());
                $runResult = $runResult->withGrading($grading);
                $this->workspaceManager->saveGradingResult($evalDir, $grading);

                $summary = $grading->getSummary();
                $this->line(sprintf(
                    '%d/%d passed (%dms, %d tokens)',
                    $summary['passed'],
                    $summary['total'],
                    $runResult->getTiming()->getDurationMs(),
                    $runResult->getTiming()->getTotalTokens(),
                ));
            } else {
                $this->line(sprintf(
                    'done (%dms, %d tokens)',
                    $runResult->getTiming()->getDurationMs(),
                    $runResult->getTiming()->getTotalTokens(),
                ));
            }

            $results[] = $runResult;
        }

        return $results;
    }
}
