<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Runner;

/**
 * Encapsulates the result of an agent execution: output text and token usage.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class AgentExecutionResult
{
    public function __construct(
        private string $output,
        private int $totalTokens,
    ) {
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }
}
