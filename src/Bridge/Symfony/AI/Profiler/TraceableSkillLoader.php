<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Profiler;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @phpstan-type SkillLoaderData array{
 *     skill?: SkillInterface,
 *     skills?: SkillInterface[],
 *     called_at: \DateTimeImmutable,
 * }
 */
final class TraceableSkillLoader implements SkillLoaderInterface, ResetInterface
{
    /**
     * @var SkillLoaderData[]
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
            'skill' => $skill,
            'called_at' => $this->clock->now(),
        ];

        return $skill;
    }

    public function loadSkills(): array
    {
        $skills = $this->skillLoader->loadSkills();

        $this->calls[] = [
            'skills' => $skills,
            'called_at' => $this->clock->now(),
        ];

        return $skills;
    }

    public function discoverMetadata(): array
    {
        return $this->skillLoader->discoverMetadata();
    }

    public function reset(): void
    {
        $this->calls = [];
    }
}
