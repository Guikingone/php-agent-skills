<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI;

use AgentSkills\Bridge\Symfony\AI\DependencyInjection\AgentSkillBundleExtension;
use Override;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentSkillsBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): AgentSkillBundleExtension
    {
        return new AgentSkillBundleExtension();
    }
}
