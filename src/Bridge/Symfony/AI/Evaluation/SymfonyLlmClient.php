<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Evaluation;

use AgentSkills\Evaluation\Grader\LlmClientInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;

/**
 * Adapts Symfony AI's PlatformInterface to the framework-agnostic LlmClientInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SymfonyLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly string $model,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $messages = new MessageBag(
            Message::forSystem($systemPrompt),
            Message::ofUser($userPrompt),
        );

        return $this->platform->invoke($this->model, $messages)->asText();
    }
}
