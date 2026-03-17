<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\NeuronAI\Evaluation;

use AgentSkills\Evaluation\Grader\LlmClientInterface;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Adapts Neuron AI's AIProviderInterface to the framework-agnostic LlmClientInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class NeuronLlmClient implements LlmClientInterface
{
    public function __construct(
        private AIProviderInterface $provider,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $this->provider->systemPrompt($systemPrompt);

        $response = $this->provider->chat(UserMessage::make($userPrompt));

        return $response->getContent() ?? '';
    }
}
