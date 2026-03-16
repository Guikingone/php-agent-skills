<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Evaluation;

use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelAgentExecutor;
use AgentSkills\Evaluation\Runner\AgentExecutionResult;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\TestCase;

final class LaravelAgentExecutorTest extends TestCase
{
    public function testExecuteReturnsOutputAndTokens()
    {
        $usage = new Usage(promptTokens: 100, completionTokens: 50);
        $response = new AgentResponse('test-invocation', 'Agent output text', $usage, new Meta());

        $agent = $this->createMock(Agent::class);
        $agent->expects($this->once())
            ->method('prompt')
            ->with('What is PHP?')
            ->willReturn($response);

        $executor = new LaravelAgentExecutor($agent);
        $result = $executor->execute('What is PHP?');

        $this->assertInstanceOf(AgentExecutionResult::class, $result);
        $this->assertSame('Agent output text', $result->getOutput());
        $this->assertSame(150, $result->getTotalTokens());
    }

    public function testExecuteWithZeroTokens()
    {
        $usage = new Usage();
        $response = new AgentResponse('test-invocation', 'Some output', $usage, new Meta());

        $agent = $this->createMock(Agent::class);
        $agent->method('prompt')->willReturn($response);

        $executor = new LaravelAgentExecutor($agent);
        $result = $executor->execute('Hello');

        $this->assertSame('Some output', $result->getOutput());
        $this->assertSame(0, $result->getTotalTokens());
    }
}
