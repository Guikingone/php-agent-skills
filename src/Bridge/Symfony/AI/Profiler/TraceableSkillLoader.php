<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Profiler;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\SkillMetadataInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @phpstan-type SkillLoaderData array{
 *     method: string,
 *     skill?: SkillInterface|null,
 *     skills?: array<string, SkillInterface>,
 *     metadata?: array<string, SkillMetadataInterface>,
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableSkillLoader implements SkillLoaderInterface, ResetInterface
{
    /**
     * @var list<SkillLoaderData>
     */
    public array $calls = [];

    public function __construct(
        private readonly SkillLoaderInterface $skillLoader,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function loadSkill(string $name): ?SkillInterface
    {
        $skill = $this->skillLoader->loadSkill($name);

        $this->calls[] = [
            'method' => 'loadSkill',
            'skill' => $skill,
            'called_at' => $this->clock->now(),
        ];

        return $skill;
    }

    public function loadSkills(): array
    {
        $skills = $this->skillLoader->loadSkills();

        $this->calls[] = [
            'method' => 'loadSkills',
            'skills' => $skills,
            'called_at' => $this->clock->now(),
        ];

        return $skills;
    }

    public function discoverMetadata(): array
    {
        $metadata = $this->skillLoader->discoverMetadata();

        $this->calls[] = [
            'method' => 'discoverMetadata',
            'metadata' => $metadata,
            'called_at' => $this->clock->now(),
        ];

        return $metadata;
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
