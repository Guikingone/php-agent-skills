<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Workspace;

use AgentSkills\Evaluation\BenchmarkResult;
use AgentSkills\Evaluation\GradingResultInterface;
use AgentSkills\Evaluation\TimingResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface WorkspaceManagerInterface
{
    public function initializeIteration(int $iteration): string;

    public function getEvalDirectory(int $iteration, string $evalName, string $configuration): string;

    public function saveTimingResult(string $evalDir, TimingResult $timingResult): void;

    public function saveGradingResult(string $evalDir, GradingResultInterface $gradingResult): void;

    public function saveBenchmarkResult(int $iteration, BenchmarkResult $benchmarkResult): void;

    public function saveOutput(string $evalDir, string $output): void;
}
