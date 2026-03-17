<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Evaluation\Workspace;

use AgentSkills\Evaluation\AssertionResult;
use AgentSkills\Evaluation\BenchmarkResult;
use AgentSkills\Evaluation\BenchmarkStatistic;
use AgentSkills\Evaluation\GradingResult;
use AgentSkills\Evaluation\TimingResult;
use AgentSkills\Evaluation\Workspace\WorkspaceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function file_get_contents;
use function json_decode;
use function random_bytes;
use function sys_get_temp_dir;

final class WorkspaceManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/workspace_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testInitializeIteration(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $dir = $manager->initializeIteration(1);

        $this->assertSame($this->tempDir . '/iteration-1', $dir);
        $this->assertDirectoryExists($dir);
    }

    public function testGetEvalDirectory(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $dir = $manager->getEvalDirectory(1, 'test-eval', 'with_skill');

        $this->assertSame($this->tempDir . '/iteration-1/eval-test-eval/with_skill', $dir);
        $this->assertDirectoryExists($dir . '/outputs');
    }

    public function testSaveTimingResult(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $manager->saveTimingResult($evalDir, new TimingResult(100, 500));

        $content = json_decode((string) file_get_contents($evalDir . '/timing.json'), true);
        $this->assertIsArray($content);
        $this->assertSame(100, $content['total_tokens']);
        $this->assertSame(500, $content['duration_ms']);
    }

    public function testSaveGradingResult(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $grading = new GradingResult([new AssertionResult('test', true, 'evidence')]);
        $manager->saveGradingResult($evalDir, $grading);

        $content = json_decode((string) file_get_contents($evalDir . '/grading.json'), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('assertion_results', $content);
        $assertionResults = $content['assertion_results'];
        $this->assertIsArray($assertionResults);
        $this->assertCount(1, $assertionResults);
        $this->assertIsArray($assertionResults[0]);
        $this->assertTrue($assertionResults[0]['passed']);
    }

    public function testSaveBenchmarkResult(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $manager->initializeIteration(1);

        $benchmark = new BenchmarkResult(
            new BenchmarkStatistic(0.8, 0.1),
            new BenchmarkStatistic(0.6, 0.15),
            new BenchmarkStatistic(500.0, 50.0),
            new BenchmarkStatistic(400.0, 40.0),
            new BenchmarkStatistic(150.0, 10.0),
            new BenchmarkStatistic(120.0, 8.0),
        );

        $manager->saveBenchmarkResult(1, $benchmark);

        $content = json_decode((string) file_get_contents($this->tempDir . '/iteration-1/benchmark.json'), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('run_summary', $content);
        $runSummary = $content['run_summary'];
        $this->assertIsArray($runSummary);
        $this->assertArrayHasKey('with_skill', $runSummary);
        $this->assertArrayHasKey('without_skill', $runSummary);
        $this->assertArrayHasKey('delta', $runSummary);
    }

    public function testSaveOutput(): void
    {
        $manager = new WorkspaceManager($this->tempDir);
        $evalDir = $manager->getEvalDirectory(1, 'test', 'with_skill');

        $manager->saveOutput($evalDir, 'Agent output text');

        $this->assertSame('Agent output text', file_get_contents($evalDir . '/outputs/output.txt'));
    }
}
