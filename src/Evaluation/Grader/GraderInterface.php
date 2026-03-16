<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Grader;

use AgentSkills\Evaluation\GradingResult;

/**
 * Grades agent output against expected assertions.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface GraderInterface
{
    /**
     * @param string[] $assertions
     */
    public function grade(string $output, array $assertions, string $expectedOutput): GradingResult;
}
