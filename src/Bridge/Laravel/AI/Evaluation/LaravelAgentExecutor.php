<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Evaluation;

use AgentSkills\Evaluation\Runner\AgentExecutionResult;
use AgentSkills\Evaluation\Runner\AgentExecutorInterface;
use Laravel\Ai\Contracts\Agent;

/**
 * Adapts Laravel AI's Agent contract to the framework-agnostic AgentExecutorInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class LaravelAgentExecutor implements AgentExecutorInterface
{
    public function __construct(
        private Agent $agent,
    ) {
    }

    public function execute(string $prompt): AgentExecutionResult
    {
        $response = $this->agent->prompt($prompt);

        $totalTokens = $response->usage->promptTokens + $response->usage->completionTokens;

        return new AgentExecutionResult($response->text, $totalTokens);
    }
}
