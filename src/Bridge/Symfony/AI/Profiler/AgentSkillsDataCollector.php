<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentSkillsDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        // TODO: Implement collect() method.
    }

    public function lateCollect(): void
    {

    }

    public function getSkills(): array
    {
        return $this->data['skills'] ?? [];
    }

    public function getName(): string
    {
        return 'agent_skills';
    }

    public function reset(): void
    {
        $this->data = [];
    }
}
