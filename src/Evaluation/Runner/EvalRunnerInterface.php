<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Runner;

use AgentSkills\Evaluation\EvalCase;
use AgentSkills\Evaluation\EvalRunResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface EvalRunnerInterface
{
    public function run(EvalCase $evalCase): EvalRunResult;
}
