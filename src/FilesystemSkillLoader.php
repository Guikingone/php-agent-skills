<?php

declare(strict_types=1);

namespace AgentSkills;

use AgentSkills\Exception\InvalidArgumentException;
use AgentSkills\Validation\SkillValidator;
use AgentSkills\Validation\SkillValidatorInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Throwable;

use function array_filter;
use function is_dir;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class FilesystemSkillLoader implements SkillLoaderInterface
{
    /**
     * @param string[] $skillDirectories Absolute paths to scan for skills
     */
    public function __construct(
        private readonly array $skillDirectories,
        private readonly SkillParserInterface $parser = new SkillParser(),
        private readonly SkillValidatorInterface $skillValidator = new SkillValidator(),
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        foreach ($this->iterateSkillDirectories() as $skillDir) {
            try {
                $skill = $this->parser->parse($skillDir);

                if ($skill->getName() !== $name) {
                    continue;
                }

                $validation = $this->skillValidator->validate($skill);

                if (!$validation->isValid()) {
                    throw new InvalidArgumentException(sprintf('The "%s" is not a valid skill.', $skill->getName()));
                }

                return $skill;
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public function loadSkills(): array
    {
        $skills = [];

        foreach ($this->iterateSkillDirectories() as $skillDir) {
            try {
                $skill = $this->parser->parse($skillDir);

                $validation = $this->skillValidator->validate($skill);

                if (!$validation->isValid()) {
                    throw new InvalidArgumentException(sprintf('The "%s" is not a valid skill.', $skill->getName()));
                }

                $skills[$skill->getName()] = $skill;
            } catch (Throwable) {
                continue;
            }
        }

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $skills = [];

        foreach ($this->iterateSkillDirectories() as $skillDir) {
            try {
                $metadata = $this->parser->parseMetadataOnly($skillDir);

                $skills[$metadata->getName()] = $metadata;
            } catch (Throwable) {
                continue;
            }
        }

        return $skills;
    }

    /**
     * Yields all valid skill directories (containing a SKILL.md) from configured base directories.
     *
     * @return iterable<string>
     */
    private function iterateSkillDirectories(): iterable
    {
        $existingDirs = array_filter(
            $this->skillDirectories,
            fn (string $dir): bool => $this->filesystem->exists($dir) && is_dir($dir),
        );

        if ([] === $existingDirs) {
            return;
        }

        $finder = (new Finder())
            ->in($existingDirs)
            ->files()
            ->name('SKILL.md')
            ->depth('== 1');

        foreach ($finder as $file) {
            yield $file->getPath();
        }
    }
}
