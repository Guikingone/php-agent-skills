<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation\Grader;

use AgentSkills\Evaluation\AssertionResult;
use AgentSkills\Evaluation\GradingResult;
use AgentSkills\Exception\RuntimeException;

use function is_array;
use function json_decode;
use function sprintf;

/**
 * Grades eval assertions using an LLM for evidence-based evaluation.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class LlmGrader implements GraderInterface
{
    public function __construct(
        private readonly LlmClientInterface $client,
    ) {
    }

    public function grade(string $output, array $assertions, string $expectedOutput): GradingResult
    {
        $assertionResults = [];

        foreach ($assertions as $assertion) {
            $assertionResults[] = $this->gradeAssertion($output, $assertion, $expectedOutput);
        }

        return new GradingResult($assertionResults);
    }

    private function gradeAssertion(string $output, string $assertion, string $expectedOutput): AssertionResult
    {
        $systemPrompt = <<<'PROMPT'
            You are a strict evaluation grader. Your task is to determine whether an agent's output satisfies a given assertion.

            Rules:
            - Evaluate ONLY the specific assertion provided.
            - Base your judgment strictly on evidence found in the output.
            - If the output partially satisfies the assertion, it should be marked as failed.
            - Respond with valid JSON only, no other text.

            Response format:
            {"passed": true/false, "evidence": "Brief explanation of why the assertion passed or failed"}
            PROMPT;

        $userPrompt = sprintf(
            "Expected output:\n%s\n\nActual output:\n%s\n\nAssertion to evaluate:\n%s",
            $expectedOutput,
            $output,
            $assertion,
        );

        $responseText = $this->client->generate($systemPrompt, $userPrompt);

        $data = json_decode($responseText, true);

        if (!is_array($data) || !isset($data['passed'], $data['evidence'])) {
            throw new RuntimeException(sprintf('Malformed grading response from LLM: "%s".', $responseText));
        }

        return new AssertionResult($assertion, (bool) $data['passed'], (string) $data['evidence']);
    }
}
