<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Captures timing and token usage for an evaluation run.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class TimingResult
{
    public function __construct(
        private int $totalTokens,
        private int $durationMs,
    ) {
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return array{total_tokens: int, duration_ms: int}
     */
    public function toArray(): array
    {
        return [
            'total_tokens' => $this->totalTokens,
            'duration_ms' => $this->durationMs,
        ];
    }
}
