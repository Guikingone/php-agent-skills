<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\NeuronAI;

use AgentSkills\Bridge\NeuronAI\Tool\SkillToolFactory;
use AgentSkills\SkillLoaderInterface;
use NeuronAI\Agent\AgentInterface;

/**
 * Trait for Neuron AI Agent classes that integrate agent skills.
 *
 * Provides convenient methods to configure skills (prompt building + tool registration)
 * without manual wiring.
 *
 * @mixin AgentInterface
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
/** @phpstan-ignore trait.unused */
trait SkillAwareAgent
{
    private ?SkillSystemPromptBuilder $skillPromptBuilder = null;

    /**
     * Configures skills for this agent.
     *
     * Call this in the agent's constructor or a setup method.
     *
     * @param string[] $activeSkills
     */
    protected function configureSkills(
        SkillLoaderInterface $loader,
        array $activeSkills = [],
        bool $includeIndex = true,
        bool $registerTools = true,
    ): void {
        $this->skillPromptBuilder = new SkillSystemPromptBuilder($loader, $activeSkills, $includeIndex);

        if ($registerTools) {
            $factory = new SkillToolFactory($loader);

            /** @var AgentInterface $this */
            $this->addTool($factory->createGetSkillsTool());

            foreach ($activeSkills as $skillName) {
                $this->addTool($factory->createGetSkillTool($skillName));
                $this->addTool($factory->createExecuteSkillScriptTool($skillName));
            }
        }
    }

    /**
     * Returns the skill instruction text, or an empty string if not configured.
     *
     * Use in your instructions() method: return $baseInstructions . $this->skillInstructions();
     */
    protected function skillInstructions(): string
    {
        if (null === $this->skillPromptBuilder) {
            return '';
        }

        return $this->skillPromptBuilder->build() ?? '';
    }
}
