<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Evaluation;

use AgentSkills\Evaluation\Runner\AgentExecutionResult;
use AgentSkills\Evaluation\Runner\AgentExecutorInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;

use function is_string;

/**
 * Adapts Symfony AI's AgentInterface to the framework-agnostic AgentExecutorInterface.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class SymfonyAgentExecutor implements AgentExecutorInterface
{
    public function __construct(
        private AgentInterface $agent,
    ) {
    }

    public function execute(string $prompt): AgentExecutionResult
    {
        $messages = new MessageBag(
            Message::ofUser($prompt),
        );

        $result = $this->agent->call($messages);

        $totalTokens = 0;
        $tokenUsage = $result->getMetadata()->get('token_usage');
        if ($tokenUsage instanceof TokenUsageInterface) {
            $totalTokens = $tokenUsage->getTotalTokens() ?? 0;
        }

        $content = $result->getContent();
        $output = is_string($content) ? $content : '';

        return new AgentExecutionResult($output, $totalTokens);
    }
}
