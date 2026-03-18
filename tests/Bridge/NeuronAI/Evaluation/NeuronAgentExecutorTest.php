<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\NeuronAI\Evaluation;

use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronAgentExecutor;
use NeuronAI\Agent\AgentHandler;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Usage;
use PHPUnit\Framework\TestCase;

final class NeuronAgentExecutorTest extends TestCase
{
    public function testExecuteReturnsOutputAndTokens(): void
    {
        $message = Message::make(MessageRole::ASSISTANT, 'Agent response text');
        $message->setUsage(new Usage(100, 50));

        $handler = $this->createMock(AgentHandler::class);
        $handler->method('getMessage')->willReturn($message);

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('chat')->willReturn($handler);

        $executor = new NeuronAgentExecutor($agent);
        $result = $executor->execute('Hello');

        $this->assertSame('Agent response text', $result->getOutput());
        $this->assertSame(150, $result->getTotalTokens());
    }

    public function testExecuteWithZeroTokens(): void
    {
        $message = Message::make(MessageRole::ASSISTANT, 'Response without usage');

        $handler = $this->createMock(AgentHandler::class);
        $handler->method('getMessage')->willReturn($message);

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('chat')->willReturn($handler);

        $executor = new NeuronAgentExecutor($agent);
        $result = $executor->execute('Hello');

        $this->assertSame('Response without usage', $result->getOutput());
        $this->assertSame(0, $result->getTotalTokens());
    }
}
