<?php

declare(strict_types=1);

namespace AgentSkills\Validation;

use AgentSkills\SkillInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class SkillValidationResult implements SkillValidationResultInterface
{
    /**
     * @param string[] $errors Validation errors (spec violations)
     * @param string[] $warnings Validation warnings (best-practice recommendations)
     */
    public function __construct(
        private SkillInterface $skill,
        private array $errors = [],
        private array $warnings = [],
    ) {
    }

    public function getSkill(): SkillInterface
    {
        return $this->skill;
    }

    public function getSkillName(): string
    {
        return $this->skill->getName();
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }
}
