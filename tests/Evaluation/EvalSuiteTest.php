<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Evaluation;

use AgentSkills\Evaluation\EvalCase;
use AgentSkills\Evaluation\EvalSuite;
use PHPUnit\Framework\TestCase;

final class EvalSuiteTest extends TestCase
{
    public function testConstruction()
    {
        $evals = [
            new EvalCase(1, 'prompt1', 'output1'),
            new EvalCase(2, 'prompt2', 'output2'),
        ];

        $suite = new EvalSuite('my-skill', $evals);

        $this->assertSame('my-skill', $suite->getSkillName());
        $this->assertCount(2, $suite->getEvals());
    }

    public function testGetEvalByIdReturnsMatchingCase()
    {
        $evals = [
            new EvalCase(1, 'prompt1', 'output1'),
            new EvalCase(2, 'prompt2', 'output2'),
        ];

        $suite = new EvalSuite('my-skill', $evals);

        $found = $suite->getEvalById(2);
        $this->assertNotNull($found);
        $this->assertSame(2, $found->getId());
        $this->assertSame('prompt2', $found->getPrompt());
    }

    public function testGetEvalByIdReturnsNullWhenNotFound()
    {
        $suite = new EvalSuite('my-skill', [new EvalCase(1, 'prompt', 'output')]);

        $this->assertNull($suite->getEvalById(99));
    }
}
