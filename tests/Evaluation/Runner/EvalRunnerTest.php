<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Evaluation\Runner;

use AgentSkills\Evaluation\EvalCase;
use AgentSkills\Evaluation\Runner\AgentExecutionResult;
use AgentSkills\Evaluation\Runner\AgentExecutorInterface;
use AgentSkills\Evaluation\Runner\EvalRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EvalRunnerTest extends TestCase
{
    public function testRunSendsPromptAndCapturesTiming()
    {
        $executor = $this->createMock(AgentExecutorInterface::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with('What is PHP?')
            ->willReturn(new AgentExecutionResult('Agent response text', 0));

        $clock = new MockClock('2026-01-01 10:00:00');

        $runner = new EvalRunner($executor, $clock);
        $evalCase = new EvalCase(1, 'What is PHP?', 'A language');

        $runResult = $runner->run($evalCase);

        $this->assertSame($evalCase, $runResult->getEvalCase());
        $this->assertSame('Agent response text', $runResult->getOutput());
        $this->assertSame(0, $runResult->getTiming()->getTotalTokens());
        $this->assertNull($runResult->getGrading());
    }

    public function testRunExtractsTokenUsage()
    {
        $executor = $this->createMock(AgentExecutorInterface::class);
        $executor->method('execute')
            ->willReturn(new AgentExecutionResult('Response', 150));

        $clock = new MockClock('2026-01-01 10:00:00');

        $runner = new EvalRunner($executor, $clock);
        $runResult = $runner->run(new EvalCase(1, 'prompt', 'expected'));

        $this->assertSame(150, $runResult->getTiming()->getTotalTokens());
    }
}
