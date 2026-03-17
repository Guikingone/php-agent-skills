<?php

declare(strict_types=1);

namespace AgentSkills\Evaluation;

use AgentSkills\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\String\UnicodeString;

use function array_filter;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function sprintf;

/**
 * Loads evaluation suites from evals/evals.json within a skill directory.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class EvalSuiteLoader implements EvalSuiteLoaderInterface
{
    public function __construct(
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function load(string $skillDirectory): EvalSuite
    {
        $evalsFile = (new UnicodeString($skillDirectory))->trimEnd('/') . '/evals/evals.json';

        if (!$this->filesystem->exists($evalsFile)) {
            throw new InvalidArgumentException(sprintf('Eval file not found at "%s".', $evalsFile));
        }

        $content = $this->filesystem->readFile($evalsFile);
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException(sprintf('Unable to parse JSON in "%s".', $evalsFile));
        }

        if (!isset($data['skill_name']) || !is_string($data['skill_name'])) {
            throw new InvalidArgumentException(sprintf('Missing or invalid "skill_name" in "%s".', $evalsFile));
        }

        if (!isset($data['evals']) || !is_array($data['evals'])) {
            throw new InvalidArgumentException(sprintf('Missing or invalid "evals" array in "%s".', $evalsFile));
        }

        $evals = [];
        foreach ($data['evals'] as $index => $evalData) {
            if (!is_array($evalData)) {
                throw new InvalidArgumentException(sprintf('Invalid eval entry at index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['id']) || !is_int($evalData['id'])) {
                throw new InvalidArgumentException(sprintf('Missing or invalid "id" at eval index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['prompt']) || !is_string($evalData['prompt'])) {
                throw new InvalidArgumentException(sprintf('Missing or invalid "prompt" at eval index %d in "%s".', $index, $evalsFile));
            }

            if (!isset($evalData['expected_output']) || !is_string($evalData['expected_output'])) {
                throw new InvalidArgumentException(sprintf('Missing or invalid "expected_output" at eval index %d in "%s".', $index, $evalsFile));
            }

            $files = [];
            if (isset($evalData['files']) && is_array($evalData['files'])) {
                $files = array_values(array_filter($evalData['files'], is_string(...)));
            }

            $assertions = [];
            if (isset($evalData['assertions']) && is_array($evalData['assertions'])) {
                $assertions = array_values(array_filter($evalData['assertions'], is_string(...)));
            }

            $evals[] = new EvalCase($evalData['id'], $evalData['prompt'], $evalData['expected_output'], $files, $assertions);
        }

        return new EvalSuite($data['skill_name'], $evals);
    }
}
