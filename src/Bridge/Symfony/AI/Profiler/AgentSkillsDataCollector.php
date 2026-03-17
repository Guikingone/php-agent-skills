<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Profiler;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

use function array_key_exists;
use function count;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentSkillsDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @param iterable<TraceableSkillLoader> $loaders
     */
    public function __construct(
        private readonly iterable $loaders,
    ) {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $skills = [];
        $calls = [];

        foreach ($this->loaders as $loader) {
            foreach ($loader->calls as $call) {
                $callData = [
                    'method' => $call['method'],
                    'called_at' => $call['called_at'],
                ];

                if (array_key_exists('skill', $call)) {
                    $skill = $call['skill'];
                    $callData['skill'] = null !== $skill ? [
                        'name' => $skill->getName(),
                        'description' => $skill->getDescription(),
                    ] : null;

                    if (null !== $skill) {
                        $skills[$skill->getName()] = [
                            'name' => $skill->getName(),
                            'description' => $skill->getDescription(),
                        ];
                    }
                }

                if (isset($call['skills'])) {
                    $callData['skills'] = [];
                    foreach ($call['skills'] as $skill) {
                        $callData['skills'][$skill->getName()] = [
                            'name' => $skill->getName(),
                            'description' => $skill->getDescription(),
                        ];
                        $skills[$skill->getName()] = [
                            'name' => $skill->getName(),
                            'description' => $skill->getDescription(),
                        ];
                    }
                }

                if (isset($call['metadata'])) {
                    $callData['metadata_count'] = count($call['metadata']);
                }

                $calls[] = $callData;
            }
        }

        $this->data = [
            'skills' => $skills,
            'calls' => $calls,
            'total_calls' => count($calls),
            'total_skills' => count($skills),
        ];
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    public function getSkills(): array
    {
        return $this->data['skills'] ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCalls(): array
    {
        return $this->data['calls'] ?? [];
    }

    public function getTotalCalls(): int
    {
        return $this->data['total_calls'] ?? 0;
    }

    public function getTotalSkills(): int
    {
        return $this->data['total_skills'] ?? 0;
    }

    public function getName(): string
    {
        return 'agent_skills';
    }

    #[Override]
    public function reset(): void
    {
        $this->data = [];

        foreach ($this->loaders as $loader) {
            $loader->reset();
        }
    }
}
