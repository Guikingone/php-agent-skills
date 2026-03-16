<?php

declare(strict_types=1);

namespace AgentSkills;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillMetadataInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getLicense(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getAllowedTools(): array;

    public function getCompatibility(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    public function getAuthor(): ?string;

    public function getVersion(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function getFrontmatter(): array;
}
