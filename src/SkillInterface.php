<?php

declare(strict_types=1);

namespace AgentSkills;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface SkillInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getBody(): string;

    public function getMetadata(): SkillMetadataInterface;

    public function loadScript(string $script): mixed;

    public function loadReference(string $reference): mixed;

    public function loadAsset(string $asset): mixed;
}
