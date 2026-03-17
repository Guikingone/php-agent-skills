<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Runner;

use AgentSkills\Evaluation\EvalCase;
use AgentSkills\Evaluation\EvalRunResult;
use AgentSkills\Evaluation\TimingResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * Runs a single eval case against an agent, measuring timing and token usage.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class EvalRunner implements EvalRunnerInterface
{
    public function __construct(
        private AgentExecutorInterface $executor,
        private ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function run(EvalCase $evalCase): EvalRunResult
    {
        $startTime = $this->clock->now();
        $executionResult = $this->executor->execute($evalCase->getPrompt());
        $endTime = $this->clock->now();

        $durationMs = (int) (($endTime->getTimestamp() - $startTime->getTimestamp()) * 1000
            + ($endTime->format('u') - $startTime->format('u')) / 1000);

        return new EvalRunResult(
            $evalCase,
            $executionResult->getOutput(),
            new TimingResult($executionResult->getTotalTokens(), $durationMs),
        );
    }
}
