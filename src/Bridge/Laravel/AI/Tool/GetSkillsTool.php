<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Tool;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

use function array_map;
use function array_values;
use function implode;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GetSkillsTool implements Tool
{
    public function __construct(
        private readonly SkillLoaderInterface $loader,
    ) {
    }

    public function description(): string
    {
        return 'Get all available skills';
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $skills = $this->loader->loadSkills();

        if ([] === $skills) {
            return 'No skills available.';
        }

        $formatted = array_values(array_map(
            static fn (SkillInterface $skill): string => sprintf("# Skill: %s\n\n%s", $skill->getName(), $skill->getBody()),
            $skills,
        ));

        return implode("\n\n---\n\n", $formatted);
    }
}
