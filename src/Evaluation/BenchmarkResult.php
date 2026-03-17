<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Aggregated benchmark results comparing with-skill vs without-skill runs.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class BenchmarkResult
{
    public function __construct(
        private BenchmarkStatistic $withSkillPassRate,
        private BenchmarkStatistic $withoutSkillPassRate,
        private BenchmarkStatistic $withSkillTime,
        private BenchmarkStatistic $withoutSkillTime,
        private BenchmarkStatistic $withSkillTokens,
        private BenchmarkStatistic $withoutSkillTokens,
    ) {
    }

    public function getWithSkillPassRate(): BenchmarkStatistic
    {
        return $this->withSkillPassRate;
    }

    public function getWithoutSkillPassRate(): BenchmarkStatistic
    {
        return $this->withoutSkillPassRate;
    }

    public function getWithSkillTime(): BenchmarkStatistic
    {
        return $this->withSkillTime;
    }

    public function getWithoutSkillTime(): BenchmarkStatistic
    {
        return $this->withoutSkillTime;
    }

    public function getWithSkillTokens(): BenchmarkStatistic
    {
        return $this->withSkillTokens;
    }

    public function getWithoutSkillTokens(): BenchmarkStatistic
    {
        return $this->withoutSkillTokens;
    }

    /**
     * @return array{pass_rate: float, time_seconds: float, tokens: float}
     */
    public function getDelta(): array
    {
        return [
            'pass_rate' => $this->withSkillPassRate->getMean() - $this->withoutSkillPassRate->getMean(),
            'time_seconds' => ($this->withSkillTime->getMean() - $this->withoutSkillTime->getMean()) / 1000,
            'tokens' => $this->withSkillTokens->getMean() - $this->withoutSkillTokens->getMean(),
        ];
    }

    /**
     * @return array{run_summary: array{with_skill: array{pass_rate: array{mean: float, stddev: float}, time_seconds: array{mean: float, stddev: float}, tokens: array{mean: float, stddev: float}}, without_skill: array{pass_rate: array{mean: float, stddev: float}, time_seconds: array{mean: float, stddev: float}, tokens: array{mean: float, stddev: float}}, delta: array{pass_rate: float, time_seconds: float, tokens: float}}}
     */
    public function toArray(): array
    {
        $withSkillTimeSeconds = (new BenchmarkStatistic($this->withSkillTime->getMean() / 1000, $this->withSkillTime->getStddev() / 1000))->toArray();
        $withoutSkillTimeSeconds = (new BenchmarkStatistic($this->withoutSkillTime->getMean() / 1000, $this->withoutSkillTime->getStddev() / 1000))->toArray();

        return [
            'run_summary' => [
                'with_skill' => [
                    'pass_rate' => $this->withSkillPassRate->toArray(),
                    'time_seconds' => $withSkillTimeSeconds,
                    'tokens' => $this->withSkillTokens->toArray(),
                ],
                'without_skill' => [
                    'pass_rate' => $this->withoutSkillPassRate->toArray(),
                    'time_seconds' => $withoutSkillTimeSeconds,
                    'tokens' => $this->withoutSkillTokens->toArray(),
                ],
                'delta' => $this->getDelta(),
            ],
        ];
    }
}
