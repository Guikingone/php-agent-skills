<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Injects discovered Agent Skills instructions into the agent's input.
 *
 * This processor prepends skill metadata summaries and/or full skill bodies
 * to the system prompt so the agent is aware of available skills.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SkillInputProcessor implements InputProcessorInterface
{
    /**
     * @param string[] $activeSkills Skill names to fully load (Level 2), empty = metadata only
     * @param bool     $includeIndex Whether to include a skill index in the system prompt
     */
    public function __construct(
        private readonly SkillLoaderInterface $loader,
        private readonly array $activeSkills = [],
        private readonly bool $includeIndex = true,
    ) {
    }

    public function processInput(Input $input): void
    {
        $systemPromptParts = [];

        if ($this->includeIndex) {
            $metadata = $this->loader->discoverMetadata();

            if ([] !== $metadata) {
                $index = "## Available Skills\n";
                foreach ($metadata as $meta) {
                    $index .= \sprintf("- **%s**: %s\n", $meta->getName(), $meta->getDescription());
                }

                $systemPromptParts[] = $index;
            }
        }

        foreach ($this->activeSkills as $skillName) {
            $skill = $this->loader->loadSkill($skillName);

            if (!$skill instanceof SkillInterface) {
                continue;
            }

            $systemPromptParts[] = \sprintf("## Skill: %s\n\n%s", $skill->getName(), $skill->getBody());
        }

        $options = $input->getOptions();

        if ([] !== $systemPromptParts) {
            $skillPrompt = "# Agent Skills\n\n".implode("\n\n", $systemPromptParts);
            $options['system_prompt'] = ($options['system_prompt'] ?? '')."\n\n".$skillPrompt;
            $input->setOptions($options);
        }
    }
}
