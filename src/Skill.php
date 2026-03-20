<?php

declare(strict_types=1);

namespace AgentSkills;

use AgentSkills\Exception\RuntimeException;
use Closure;

use function implode;
use function sprintf;

/**
 * Represents a fully loaded Agent Skill.
 *
 * A skill is a directory containing a SKILL.md file with YAML frontmatter
 * and Markdown instructions, plus optional scripts/, references/, and assets/ directories.
 *
 * @see https://agentskills.io/specification
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class Skill implements SkillInterface
{
    public function __construct(
        private string $body,
        private SkillMetadataInterface $metadata,
        private ?Closure $scriptsLoader = null,
        private ?Closure $referencesLoader = null,
        private ?Closure $assetsLoader = null,
        private ?Closure $scriptsLister = null,
        private ?Closure $referencesLister = null,
        private ?Closure $assetsLister = null,
    ) {
    }

    public function getName(): string
    {
        return $this->metadata->getName();
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDescription(): string
    {
        return $this->metadata->getDescription();
    }

    public function getMetadata(): SkillMetadataInterface
    {
        return $this->metadata;
    }

    /**
     * Returns the absolute path to a script file.
     *
     * @throws RuntimeException if the script does not exist
     *
     * @return string The absolute path to the script
     */
    public function loadScript(string $script): mixed
    {
        if (!$this->scriptsLoader instanceof Closure) {
            throw new RuntimeException('No scripts loader configured for this skill.');
        }

        return ($this->scriptsLoader)($script);
    }

    public function loadReference(string $reference): mixed
    {
        if (!$this->referencesLoader instanceof Closure) {
            return null;
        }

        return ($this->referencesLoader)($reference);
    }

    public function loadAsset(string $asset): mixed
    {
        if (!$this->assetsLoader instanceof Closure) {
            return null;
        }

        return ($this->assetsLoader)($asset);
    }

    /**
     * @return string[]
     */
    public function listScripts(): array
    {
        if (!$this->scriptsLister instanceof Closure) {
            return [];
        }

        return ($this->scriptsLister)();
    }

    /**
     * @return string[]
     */
    public function listReferences(): array
    {
        if (!$this->referencesLister instanceof Closure) {
            return [];
        }

        return ($this->referencesLister)();
    }

    /**
     * @return string[]
     */
    public function listAssets(): array
    {
        if (!$this->assetsLister instanceof Closure) {
            return [];
        }

        return ($this->assetsLister)();
    }

    public function getResourceListing(): string
    {
        $scripts = $this->listScripts();
        $references = $this->listReferences();
        $assets = $this->listAssets();

        if ([] === $scripts && [] === $references && [] === $assets) {
            return '';
        }

        $sections = [];

        if ([] !== $scripts) {
            $items = '';
            foreach ($scripts as $script) {
                $items .= sprintf("- %s\n", $script);
            }
            $sections[] = "### Scripts\n" . $items;
        }

        if ([] !== $references) {
            $items = '';
            foreach ($references as $reference) {
                $items .= sprintf("- %s\n", $reference);
            }
            $sections[] = "### References\n" . $items;
        }

        if ([] !== $assets) {
            $items = '';
            foreach ($assets as $asset) {
                $items .= sprintf("- %s\n", $asset);
            }
            $sections[] = "### Assets\n" . $items;
        }

        return "\n\n## Available Resources\n\n" . implode("\n", $sections);
    }
}
