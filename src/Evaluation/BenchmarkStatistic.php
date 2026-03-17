<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Represents a mean/standard deviation pair for a benchmark metric.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class BenchmarkStatistic
{
    public function __construct(
        private float $mean,
        private float $stddev,
    ) {
    }

    public function getMean(): float
    {
        return $this->mean;
    }

    public function getStddev(): float
    {
        return $this->stddev;
    }

    /**
     * @return array{mean: float, stddev: float}
     */
    public function toArray(): array
    {
        return [
            'mean' => $this->mean,
            'stddev' => $this->stddev,
        ];
    }
}
