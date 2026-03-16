<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Grader;

/**
 * Abstracts LLM platform calls for grading purposes.
 *
 * Implementations wrap framework-specific platform APIs (Symfony AI, Laravel AI, etc.)
 * and expose a simple prompt-in / text-out contract.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface LlmClientInterface
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}
