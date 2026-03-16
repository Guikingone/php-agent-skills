<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Tool;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function pathinfo;
use function sprintf;

use const PATHINFO_EXTENSION;
use const PHP_BINARY;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ExecuteSkillScriptTool implements Tool
{
    public function __construct(
        private readonly SkillLoaderInterface $loader,
        private readonly string $skillName,
    ) {
    }

    public function description(): string
    {
        return sprintf('Execute a script from the "%s" skill', $this->skillName);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'script' => $schema->string()
                ->description('The script filename (e.g., "setup.sh", "analyze.py")')
                ->required(),
            'arguments' => $schema->array()
                ->description('Optional command-line arguments to pass to the script')
                ->nullable(),
            'timeout' => $schema->integer()
                ->description('Maximum execution time in seconds (default: 60)')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return sprintf('Skill "%s" not found.', $this->skillName);
        }

        $script = $request['script'];
        $arguments = $request['arguments'] ?? [];
        $timeout = $request['timeout'] ?? 60;

        try {
            $scriptPath = $skill->loadScript($script);
        } catch (RuntimeException $e) {
            return sprintf('Error loading script "%s": "%s".', $script, $e->getMessage());
        }

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

    private function getInterpreter(string $scriptPath): ?string
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
