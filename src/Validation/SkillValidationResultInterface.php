<?php

declare(strict_types=1);

namespace AgentSkills\Validation;

use AgentSkills\SkillInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillValidationResultInterface
{
    public function getSkill(): SkillInterface;

    public function isValid(): bool;

    /**
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * @return string[]
     */
    public function getWarnings(): array;

    public function hasWarnings(): bool;
}
