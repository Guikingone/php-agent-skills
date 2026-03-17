<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Evaluation\Grader;

use AgentSkills\Evaluation\Grader\LlmClientInterface;
use AgentSkills\Evaluation\Grader\LlmGrader;
use AgentSkills\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

final class LlmGraderTest extends TestCase
{
    public function testGradePassingAssertion(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn('{"passed": true, "evidence": "Output mentions PHP correctly"}');

        $grader = new LlmGrader($client);
        $result = $grader->grade('PHP is a language', ['Output mentions PHP'], 'PHP is a programming language');

        $this->assertCount(1, $result->getAssertionResults());
        $this->assertTrue($result->getAssertionResults()[0]->isPassed());
        $this->assertSame('Output mentions PHP correctly', $result->getAssertionResults()[0]->getEvidence());
    }

    public function testGradeFailingAssertion(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn('{"passed": false, "evidence": "Output does not mention Java"}');

        $grader = new LlmGrader($client);
        $result = $grader->grade('PHP is a language', ['Output mentions Java'], 'Java is a programming language');

        $this->assertCount(1, $result->getAssertionResults());
        $this->assertFalse($result->getAssertionResults()[0]->isPassed());
    }

    public function testGradeMultipleAssertions(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->exactly(2))
            ->method('generate')
            ->willReturnOnConsecutiveCalls(
                '{"passed": true, "evidence": "Found it"}',
                '{"passed": false, "evidence": "Not found"}',
            );

        $grader = new LlmGrader($client);
        $result = $grader->grade('output', ['assertion1', 'assertion2'], 'expected');

        $this->assertCount(2, $result->getAssertionResults());
        $summary = $result->getSummary();
        $this->assertSame(1, $summary['passed']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0.5, $summary['pass_rate']);
    }

    public function testGradeThrowsOnMalformedResponse(): void
    {
        $client = $this->createMock(LlmClientInterface::class);
        $client->expects($this->once())
            ->method('generate')
            ->willReturn('not json at all');

        $grader = new LlmGrader($client);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed grading response');

        $grader->grade('output', ['assertion'], 'expected');
    }
}
