<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Evaluation;

use AgentSkills\Evaluation\EvalSuiteLoader;
use AgentSkills\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function json_encode;
use function random_bytes;
use function sys_get_temp_dir;

use const JSON_THROW_ON_ERROR;

final class EvalSuiteLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/eval_suite_loader_test_' . bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir . '/evals');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testLoadValidJson(): void
    {
        $data = [
            'skill_name' => 'test-skill',
            'evals' => [
                ['id' => 1, 'prompt' => 'What is PHP?', 'expected_output' => 'A language'],
                ['id' => 2, 'prompt' => 'Explain OOP', 'expected_output' => 'Object-oriented programming', 'files' => ['src/Foo.php'], 'assertions' => ['Mentions classes']],
            ],
        ];

        (new Filesystem())->dumpFile($this->tempDir . '/evals/evals.json', json_encode($data, JSON_THROW_ON_ERROR));

        $suite = (new EvalSuiteLoader())->load($this->tempDir);

        $this->assertSame('test-skill', $suite->getSkillName());
        $this->assertCount(2, $suite->getEvals());

        $first = $suite->getEvalById(1);
        $this->assertNotNull($first);
        $this->assertSame('What is PHP?', $first->getPrompt());
        $this->assertSame([], $first->getFiles());

        $second = $suite->getEvalById(2);
        $this->assertNotNull($second);
        $this->assertSame(['src/Foo.php'], $second->getFiles());
        $this->assertSame(['Mentions classes'], $second->getAssertions());
    }

    public function testLoadThrowsWhenFileMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Eval file not found');

        (new EvalSuiteLoader())->load($this->tempDir . '/nonexistent');
    }

    public function testLoadThrowsOnMalformedJson(): void
    {
        (new Filesystem())->dumpFile($this->tempDir . '/evals/evals.json', '{invalid json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse JSON');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingSkillName(): void
    {
        $data = ['evals' => [['id' => 1, 'prompt' => 'test', 'expected_output' => 'out']]];

        (new Filesystem())->dumpFile($this->tempDir . '/evals/evals.json', json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "skill_name"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingEvals(): void
    {
        $data = ['skill_name' => 'test-skill'];

        (new Filesystem())->dumpFile($this->tempDir . '/evals/evals.json', json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "evals"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }

    public function testLoadThrowsOnMissingRequiredEvalFields(): void
    {
        $data = [
            'skill_name' => 'test-skill',
            'evals' => [['id' => 1, 'prompt' => 'test']],
        ];

        (new Filesystem())->dumpFile($this->tempDir . '/evals/evals.json', json_encode($data, JSON_THROW_ON_ERROR));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid "expected_output"');

        (new EvalSuiteLoader())->load($this->tempDir);
    }
}
