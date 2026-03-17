<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final readonly class DataCollector implements DataCollectorInterface
{
    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        // TODO: Implement collect() method.
    }

    public function getName(): string
    {
        return 'agent_skills';
    }

    public function reset(): void
    {
        // TODO: Implement reset() method.
    }
}
