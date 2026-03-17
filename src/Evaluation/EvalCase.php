<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Represents a single evaluation test case from an evals.json file.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class EvalCase
{
    /**
     * @param int $id Unique identifier within the eval suite
     * @param string $prompt The user prompt to send to the agent
     * @param string $expectedOutput The expected output for comparison
     * @param string[] $files Optional files to provide as context
     * @param string[] $assertions Assertions to grade the output against
     */
    public function __construct(
        private int $id,
        private string $prompt,
        private string $expectedOutput,
        private array $files = [],
        private array $assertions = [],
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getExpectedOutput(): string
    {
        return $this->expectedOutput;
    }

    /**
     * @return string[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return string[]
     */
    public function getAssertions(): array
    {
        return $this->assertions;
    }
}
