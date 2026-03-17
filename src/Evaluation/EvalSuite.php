<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

/**
 * Represents a complete evaluation suite loaded from evals/evals.json.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class EvalSuite
{
    /**
     * @param string $skillName The skill this suite evaluates
     * @param EvalCase[] $evals The evaluation cases
     */
    public function __construct(
        private string $skillName,
        private array $evals,
    ) {
    }

    public function getSkillName(): string
    {
        return $this->skillName;
    }

    /**
     * @return EvalCase[]
     */
    public function getEvals(): array
    {
        return $this->evals;
    }

    public function getEvalById(int $id): ?EvalCase
    {
        foreach ($this->evals as $eval) {
            if ($eval->getId() === $id) {
                return $eval;
            }
        }

        return null;
    }
}
