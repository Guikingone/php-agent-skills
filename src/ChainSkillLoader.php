<?php

declare(strict_types=1);

namespace AgentSkills;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class ChainSkillLoader implements SkillLoaderInterface
{
    /**
     * @param SkillLoaderInterface[] $loaders
     */
    public function __construct(
        private array $loaders,
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        foreach ($this->loaders as $loader) {
            $skill = $loader->loadSkill($name);

            if (!$skill instanceof SkillInterface) {
                continue;
            }

            return $skill;
        }

        return null;
    }

    public function loadSkills(): array
    {
        $skills = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->loadSkills() as $name => $skill) {
                $skills[$name] ??= $skill;
            }
        }

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $metadata = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->discoverMetadata() as $name => $skillMetadata) {
                $metadata[$name] ??= $skillMetadata;
            }
        }

        return $metadata;
    }
}
