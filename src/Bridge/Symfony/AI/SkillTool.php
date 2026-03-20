<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use RuntimeException;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function array_map;
use function is_string;
use function pathinfo;
use function sprintf;

use const PATHINFO_EXTENSION;
use const PHP_BINARY;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsTool('get_skill', 'Load a skill by name', method: 'loadSkill')]
#[AsTool('get_skills', 'Get all available skills', method: 'loadSkills')]
#[AsTool('execute_skill_script', 'Execute a script from a skill', method: 'executeScript')]
final readonly class SkillTool
{
    public function __construct(
        private SkillLoaderInterface $loader,
        private string $skillName,
    ) {
    }

    /**
     * @param string|null $reference Optional relative path to a reference file within the skill
     */
    public function loadSkill(?string $reference = null): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return sprintf('Skill "%s" not found.', $this->skillName);
        }

        $output = sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody()) . $skill->getResourceListing();

        if (null !== $reference) {
            try {
                $referenceContent = $skill->loadReference($reference);
                if (is_string($referenceContent)) {
                    $output .= sprintf("\n\n## Reference: %s\n\n%s", $reference, $referenceContent);
                }
            } catch (RuntimeException $e) {
                $output .= sprintf("\n\n> Reference \"%s\" could not be loaded: %s", $reference, $e->getMessage());
            }
        }

        return $output;
    }

    /**
     * @return string[]
     */
    public function loadSkills(): array
    {
        $skills = $this->loader->loadSkills();

        if ([] === $skills) {
            return [];
        }

        return array_map(
            static fn (SkillInterface $skill): string => sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody()) . $skill->getResourceListing(),
            $skills,
        );
    }

    /**
     * Execute a script from the skill.
     *
     * @param string $script The script filename (e.g., 'setup.sh', 'analyze.py')
     * @param string[] $arguments Optional command-line arguments to pass to the script
     * @param int $timeout Maximum execution time in seconds (default: 60)
     *
     * @return string The script output (stdout and stderr combined)
     */
    public function executeScript(string $script, array $arguments = [], int $timeout = 60): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return sprintf('Skill "%s" not found.', $this->skillName);
        }

        try {
            $scriptPath = $skill->loadScript($script);
        } catch (RuntimeException $e) {
            return sprintf('Error loading script "%s": "%s".', $script, $e->getMessage());
        }

        if (!is_string($scriptPath)) {
            return sprintf('Script "%s" returned an invalid path.', $script);
        }

        // Determine the interpreter based on file extension
        $interpreter = $this->getInterpreter($scriptPath);
        $command = $interpreter ? [$interpreter, $scriptPath, ...$arguments] : [$scriptPath, ...$arguments];

        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->mustRun();

            return sprintf("# Script execution: %s\n\n## Output\n\n```\n%s\n```", $script, $process->getOutput());
        } catch (ProcessFailedException) {
            return sprintf(
                "# Script execution failed: %s\n\n## Error\n\n```\n%s\n```\n\n## Output\n\n```\n%s\n```",
                $script,
                $process->getErrorOutput(),
                $process->getOutput(),
            );
        }
    }

    /**
     * Determine the interpreter to use based on the script file extension.
     */
    private function getInterpreter(string $scriptPath): ?string
    {
        $extension = pathinfo($scriptPath, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => PHP_BINARY,
            'py' => 'python3',
            'sh' => 'bash',
            'js' => 'node',
            'rb' => 'ruby',
            default => null, // Try to execute directly (must have execute permissions)
        };
    }
}
