<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
