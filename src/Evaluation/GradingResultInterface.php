<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface GradingResultInterface
{
    /**
     * @return AssertionResult[]
     */
    public function getAssertionResults(): array;

    /**
     * @return array{passed: int, failed: int, total: int, pass_rate: float}
     */
    public function getSummary(): array;

    /**
     * @return array{assertion_results: list<array{text: string, passed: bool, evidence: string}>, summary: array{passed: int, failed: int, total: int, pass_rate: float}}
     */
    public function toArray(): array;
}
