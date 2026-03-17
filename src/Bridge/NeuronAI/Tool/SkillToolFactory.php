<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\NeuronAI\Tool;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;
use NeuronAI\Tools\ToolProperty;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function pathinfo;
use function sprintf;

use const PATHINFO_EXTENSION;
use const PHP_BINARY;

/**
 * Creates Neuron AI Tool instances for agent skill operations.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class SkillToolFactory
{
    public function __construct(
        private SkillLoaderInterface $loader,
    ) {
    }

    /**
     * Creates a Tool that loads a specific skill by name, with optional reference support.
     */
    public function createGetSkillTool(string $skillName): ToolInterface
    {
        $loader = $this->loader;

        $tool = Tool::make(
            'get_skill_' . $skillName,
            sprintf('Load the "%s" skill by name', $skillName),
        );

        $tool->addProperty(
            ToolProperty::make('reference', PropertyType::STRING, 'Optional relative path to a reference file within the skill', false),
        );

        $tool->setCallable(static function (?string $reference = null) use ($loader, $skillName): string {
            $skill = $loader->loadSkill($skillName);

            if (!$skill instanceof SkillInterface) {
                return sprintf('Skill "%s" not found.', $skillName);
            }

            $output = sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody());

            if (is_string($reference) && '' !== $reference) {
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
        });

        return $tool;
    }

    /**
     * Creates a Tool that lists all available skills.
     */
    public function createGetSkillsTool(): ToolInterface
    {
        $loader = $this->loader;

        $tool = Tool::make('get_skills', 'Get all available skills');

        $tool->setCallable(static function () use ($loader): string {
            $skills = $loader->loadSkills();

            if ([] === $skills) {
                return 'No skills available.';
            }

            $formatted = array_values(array_map(
                static fn (SkillInterface $skill): string => sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody()),
                $skills,
            ));

            return implode("\n\n---\n\n", $formatted);
        });

        return $tool;
    }

    /**
     * Creates a Tool that executes a script from a specific skill.
     */
    public function createExecuteSkillScriptTool(string $skillName): ToolInterface
    {
        $loader = $this->loader;

        $tool = Tool::make(
            'execute_skill_script_' . $skillName,
            sprintf('Execute a script from the "%s" skill', $skillName),
        );

        $tool->addProperty(
            ToolProperty::make('script', PropertyType::STRING, 'The script filename (e.g., "setup.sh", "analyze.py")', true),
        );
        $tool->addProperty(
            ArrayProperty::make('arguments', 'Optional command-line arguments to pass to the script', false),
        );
        $tool->addProperty(
            ToolProperty::make('timeout', PropertyType::INTEGER, 'Maximum execution time in seconds (default: 60)', false),
        );

        $tool->setCallable(static function (string $script, ?array $arguments = null, ?int $timeout = null) use ($loader, $skillName): string {
            $skill = $loader->loadSkill($skillName);

            if (!$skill instanceof SkillInterface) {
                return sprintf('Skill "%s" not found.', $skillName);
            }

            if ('' === $script) {
                return 'Missing or invalid "script" parameter.';
            }

            $safeArguments = is_array($arguments) ? array_values(array_filter($arguments, is_string(...))) : [];
            $safeTimeout = is_int($timeout) ? (float) $timeout : 60.0;

            try {
                $scriptPath = $skill->loadScript($script);
            } catch (RuntimeException $e) {
                return sprintf('Error loading script "%s": "%s".', $script, $e->getMessage());
            }

            if (!is_string($scriptPath)) {
                return sprintf('Script "%s" returned an invalid path.', $script);
            }

            $interpreter = self::getInterpreter($scriptPath);
            $command = null !== $interpreter ? [$interpreter, $scriptPath, ...$safeArguments] : [$scriptPath, ...$safeArguments];

            $process = new Process($command);
            $process->setTimeout($safeTimeout);

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
        });

        return $tool;
    }

    private static function getInterpreter(string $scriptPath): ?string
    {
        $extension = pathinfo($scriptPath, PATHINFO_EXTENSION);

        return match ($extension) {
            'php' => PHP_BINARY,
            'py' => 'python3',
            'sh' => 'bash',
            'js' => 'node',
            'rb' => 'ruby',
            default => null,
        };
    }
}
