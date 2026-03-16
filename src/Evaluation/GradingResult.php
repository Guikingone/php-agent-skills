<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

use function array_map;
use function count;

/**
 * Aggregates assertion results into a grading summary.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GradingResult implements GradingResultInterface
{
    /**
     * @param AssertionResult[] $assertionResults
     */
    public function __construct(
        private readonly array $assertionResults,
    ) {
    }

    public function getAssertionResults(): array
    {
        return $this->assertionResults;
    }

    public function getSummary(): array
    {
        $total = count($this->assertionResults);
        $passed = 0;

        foreach ($this->assertionResults as $result) {
            if ($result->isPassed()) {
                ++$passed;
            }
        }

        return [
            'passed' => $passed,
            'failed' => $total - $passed,
            'total' => $total,
            'pass_rate' => 0 === $total ? 0.0 : $passed / $total,
        ];
    }

    public function toArray(): array
    {
        return [
            'assertion_results' => array_map(
                static fn (AssertionResult $r): array => $r->toArray(),
                $this->assertionResults,
            ),
            'summary' => $this->getSummary(),
        ];
    }
}
