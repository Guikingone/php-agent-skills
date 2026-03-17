<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Evaluation;

use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelLlmClient;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\TestCase;

final class LaravelLlmClientTest extends TestCase
{
    public function testGenerateReturnsText(): void
    {
        $response = new AgentResponse('inv-1', 'Graded output', new Usage(), new Meta());

        $textProvider = $this->createMock(TextProvider::class);
        $textProvider->expects($this->once())
            ->method('prompt')
            ->with($this->callback(static fn (AgentPrompt $prompt): bool => 'Grade this output' === $prompt->prompt && 'gpt-4o-mini' === $prompt->model))
            ->willReturn($response);

        $ai = $this->createMock(AiManager::class);
        $ai->expects($this->once())
            ->method('textProvider')
            ->with('openai')
            ->willReturn($textProvider);

        $client = new LaravelLlmClient($ai, 'openai', 'gpt-4o-mini');
        $result = $client->generate('You are a grader.', 'Grade this output');

        $this->assertSame('Graded output', $result);
    }
}
