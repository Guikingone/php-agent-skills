<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Runner;

/**
 * Abstracts agent execution for evaluation purposes.
 *
 * Implementations wrap framework-specific agent APIs (Symfony AI, Laravel AI, etc.)
 * and expose a simple prompt-in / result-out contract.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface AgentExecutorInterface
{
    public function execute(string $prompt): AgentExecutionResult;
}
