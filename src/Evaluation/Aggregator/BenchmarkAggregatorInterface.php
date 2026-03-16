<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Aggregator;

use AgentSkills\Evaluation\BenchmarkResult;
use AgentSkills\Evaluation\EvalRunResult;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface BenchmarkAggregatorInterface
{
    /**
     * @param EvalRunResult[] $withSkillResults
     * @param EvalRunResult[] $withoutSkillResults
     */
    public function aggregate(array $withSkillResults, array $withoutSkillResults): BenchmarkResult;
}
