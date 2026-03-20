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

    /**
     * @return string[] List of available script filenames
     */
    public function listScripts(): array;

    /**
     * @return string[] List of available reference filenames
     */
    public function listReferences(): array;

    /**
     * @return string[] List of available asset filenames
     */
    public function listAssets(): array;

    /**
     * Returns a formatted Markdown listing of all available resources.
     *
     * Used for structured activation output per the AgentSkills spec.
     */
    public function getResourceListing(): string;
}
