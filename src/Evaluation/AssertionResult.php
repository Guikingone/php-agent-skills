<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Represents the grading result of a single assertion.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AssertionResult
{
    public function __construct(
        private readonly string $text,
        private readonly bool $passed,
        private readonly string $evidence,
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function getEvidence(): string
    {
        return $this->evidence;
    }

    /**
     * @return array{text: string, passed: bool, evidence: string}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'passed' => $this->passed,
            'evidence' => $this->evidence,
        ];
    }
}
