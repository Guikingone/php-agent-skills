<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
