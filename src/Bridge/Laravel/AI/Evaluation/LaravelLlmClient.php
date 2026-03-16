<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Evaluation;

use AgentSkills\Evaluation\Grader\LlmClientInterface;
use Laravel\Ai\AiManager;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Adapts Laravel AI's text generation to the framework-agnostic LlmClientInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LaravelLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly AiManager $ai,
        private readonly string $provider,
        private readonly string $model,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $provider = $this->ai->textProvider($this->provider);

        $agent = new AnonymousAgent($systemPrompt, [], []);

        $prompt = new AgentPrompt(
            $agent,
            $userPrompt,
            [],
            $provider,
            $this->model,
        );

        $response = $provider->prompt($prompt);

        return $response->text;
    }
}
