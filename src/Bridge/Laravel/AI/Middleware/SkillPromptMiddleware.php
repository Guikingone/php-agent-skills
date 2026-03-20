<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Middleware;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

use function implode;
use function sprintf;

/**
 * Injects discovered Agent Skills instructions into the agent's prompt.
 *
 * This middleware prepends skill metadata summaries and/or full skill bodies
 * to the prompt so the agent is aware of available skills.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class SkillPromptMiddleware
{
    /**
     * @param string[] $activeSkills Skill names to fully load (Level 2), empty = metadata only
     * @param bool $includeIndex Whether to include a skill index in the prompt
     */
    public function __construct(
        private SkillLoaderInterface $loader,
        private array $activeSkills = [],
        private bool $includeIndex = true,
    ) {
    }

    public function handle(AgentPrompt $prompt, Closure $next): mixed
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

            $systemPromptParts[] = sprintf("## Skill: %s\n\n%s", $skill->getName(), $skill->getBody()) . $skill->getResourceListing();
        }

        if ([] !== $systemPromptParts) {
            $skillPrompt = "# Agent Skills\n\n" . implode("\n\n", $systemPromptParts);
            $prompt = $prompt->prepend($skillPrompt);
        }

        return $next($prompt);
    }
}
