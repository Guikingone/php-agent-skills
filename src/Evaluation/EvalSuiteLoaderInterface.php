<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

use AgentSkills\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface EvalSuiteLoaderInterface
{
    /**
     * @throws InvalidArgumentException When the eval file is missing or malformed
     */
    public function load(string $skillDirectory): EvalSuite;
}
