<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\NeuronAI\Evaluation;

use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronLlmClient;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Providers\AIProviderInterface;
use PHPUnit\Framework\TestCase;

final class NeuronLlmClientTest extends TestCase
{
    public function testGenerateReturnsText(): void
    {
        $response = Message::make(MessageRole::ASSISTANT, 'Generated text');

        $provider = $this->createMock(AIProviderInterface::class);
        $provider->expects($this->once())
            ->method('systemPrompt')
            ->with('You are a grader.')
            ->willReturn($provider);
        $provider->expects($this->once())
            ->method('chat')
            ->willReturn($response);

        $client = new NeuronLlmClient($provider);
        $result = $client->generate('You are a grader.', 'Grade this output.');

        $this->assertSame('Generated text', $result);
    }

    public function testGenerateReturnsEmptyStringWhenNoContent(): void
    {
        $response = Message::make(MessageRole::ASSISTANT);

        $provider = $this->createMock(AIProviderInterface::class);
        $provider->method('systemPrompt')->willReturn($provider);
        $provider->method('chat')->willReturn($response);

        $client = new NeuronLlmClient($provider);
        $result = $client->generate('System prompt', 'User prompt');

        $this->assertSame('', $result);
    }
}
