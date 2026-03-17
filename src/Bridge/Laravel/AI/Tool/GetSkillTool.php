<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Tool;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;

use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class GetSkillTool implements Tool
{
    public function __construct(
        private SkillLoaderInterface $loader,
        private string $skillName,
    ) {
    }

    public function description(): string
    {
        return sprintf('Load the "%s" skill by name', $this->skillName);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('Optional relative path to a reference file within the skill')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $skill = $this->loader->loadSkill($this->skillName);

        if (!$skill instanceof SkillInterface) {
            return sprintf('Skill "%s" not found.', $this->skillName);
        }

        $output = sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody());

        $reference = $request['reference'] ?? null;
        if (null !== $reference) {
            try {
                $referenceContent = $skill->loadReference($reference);
                $output .= sprintf("\n\n## Reference: %s\n\n%s", $reference, $referenceContent);
            } catch (RuntimeException $e) {
                $output .= sprintf("\n\n> Reference \"%s\" could not be loaded: %s", $reference, $e->getMessage());
            }
        }

        return $output;
    }
}
