<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Runner;

/**
 * Encapsulates the result of an agent execution: output text and token usage.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentExecutionResult
{
    public function __construct(
        private readonly string $output,
        private readonly int $totalTokens,
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
