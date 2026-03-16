<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Aggregator;

use AgentSkills\Evaluation\BenchmarkResult;
use AgentSkills\Evaluation\BenchmarkStatistic;
use AgentSkills\Evaluation\EvalRunResult;
use AgentSkills\Evaluation\GradingResultInterface;

use function array_map;
use function array_sum;
use function count;
use function sqrt;

/**
 * Computes aggregate statistics from evaluation run results.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class BenchmarkAggregator implements BenchmarkAggregatorInterface
{
    public function aggregate(array $withSkillResults, array $withoutSkillResults): BenchmarkResult
    {
        return new BenchmarkResult(
            $this->computeStatistic($this->extractPassRates($withSkillResults)),
            $this->computeStatistic($this->extractPassRates($withoutSkillResults)),
            $this->computeStatistic($this->extractDurations($withSkillResults)),
            $this->computeStatistic($this->extractDurations($withoutSkillResults)),
            $this->computeStatistic($this->extractTokens($withSkillResults)),
            $this->computeStatistic($this->extractTokens($withoutSkillResults)),
        );
    }

    /**
     * @param float[] $values
     */
    private function computeStatistic(array $values): BenchmarkStatistic
    {
        if ([] === $values) {
            return new BenchmarkStatistic(0.0, 0.0);
        }

        $count = count($values);
        $mean = array_sum($values) / $count;

        if (1 === $count) {
            return new BenchmarkStatistic($mean, 0.0);
        }

        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        $variance /= $count;

        return new BenchmarkStatistic($mean, sqrt($variance));
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractPassRates(array $results): array
    {
        $rates = [];

        foreach ($results as $result) {
            $grading = $result->getGrading();
            if (!$grading instanceof GradingResultInterface) {
                continue;
            }

            $rates[] = $grading->getSummary()['pass_rate'];
        }

        return $rates;
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractDurations(array $results): array
    {
        return array_map(
            static fn (EvalRunResult $r): float => (float) $r->getTiming()->getDurationMs(),
            $results,
        );
    }

    /**
     * @param EvalRunResult[] $results
     *
     * @return float[]
     */
    private function extractTokens(array $results): array
    {
        return array_map(
            static fn (EvalRunResult $r): float => (float) $r->getTiming()->getTotalTokens(),
            $results,
        );
    }
}
