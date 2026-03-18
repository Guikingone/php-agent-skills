<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\NeuronAI\Evaluation;

use AgentSkills\Evaluation\Runner\AgentExecutionResult;
use AgentSkills\Evaluation\Runner\AgentExecutorInterface;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * Adapts Neuron AI's AgentInterface to the framework-agnostic AgentExecutorInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class NeuronAgentExecutor implements AgentExecutorInterface
{
    public function __construct(
        private AgentInterface $agent,
    ) {
    }

    public function execute(string $prompt): AgentExecutionResult
    {
        $response = $this->agent->chat(UserMessage::make($prompt))->getMessage();

        $output = $response->getContent() ?? '';
        $totalTokens = 0;

        $usage = $response->getUsage();
        if (null !== $usage) {
            $totalTokens = $usage->getTotal();
        }

        return new AgentExecutionResult($output, $totalTokens);
    }
}
