<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\NeuronAI;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;

use function implode;
use function sprintf;

/**
 * Builds skill instruction text for inclusion in a Neuron AI agent's system prompt.
 *
 * This builder mirrors the prompt-building logic from both the Symfony AI
 * SkillInputProcessor and the Laravel AI SkillPromptMiddleware.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class SkillSystemPromptBuilder
{
    /**
     * @param string[] $activeSkills Skill names to fully load (Level 2), empty = metadata only
     * @param bool $includeIndex Whether to include a skill index in the output
     */
    public function __construct(
        private SkillLoaderInterface $loader,
        private array $activeSkills = [],
        private bool $includeIndex = true,
    ) {
    }

    /**
     * Builds the skill instruction text for inclusion in a system prompt.
     *
     * Returns null when no skills are available and no active skills are configured,
     * to let callers easily skip concatenation.
     */
    public function build(): ?string
    {
        $systemPromptParts = [];

        if ($this->includeIndex) {
            $metadata = $this->loader->discoverMetadata();

            if ([] !== $metadata) {
                $index = "## Available Skills\n";
                foreach ($metadata as $meta) {
                    $index .= sprintf("- **%s**: %s\n", $meta->getName(), $meta->getDescription());
                }

                $systemPromptParts[] = $index;
            }
        }

        foreach ($this->activeSkills as $skillName) {
            $skill = $this->loader->loadSkill($skillName);

            if (!$skill instanceof SkillInterface) {
                continue;
            }

            $systemPromptParts[] = sprintf("## Skill: %s\n\n%s", $skill->getName(), $skill->getBody());
        }

        if ([] === $systemPromptParts) {
            return null;
        }

        return "# Agent Skills\n\n" . implode("\n\n", $systemPromptParts);
    }
}
