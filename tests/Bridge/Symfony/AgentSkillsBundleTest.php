<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Symfony;

use AgentSkills\Bridge\Symfony\AI\AgentSkillsBundle;
use PHPUnit\Framework\TestCase;

final class AgentSkillsBundleTest extends TestCase
{
    public function testExtensionIsReturned(): void
    {
        $agentSkillsBundle = new AgentSkillsBundle();

        self::assertSame('agent_skill_bundle', $agentSkillsBundle->getContainerExtension()->getAlias());
    }
}
