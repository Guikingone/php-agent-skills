<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Symfony\AI\Profiler;

use AgentSkills\Bridge\Symfony\AI\Profiler\AgentSkillsDataCollector;
use AgentSkills\Bridge\Symfony\AI\Profiler\TraceableSkillLoader;
use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AgentSkillsDataCollectorTest extends TestCase
{
    public function testGetNameReturnsAgentSkills(): void
    {
        $collector = new AgentSkillsDataCollector([]);

        $this->assertSame('agent_skills', $collector->getName());
    }

    public function testAccessorsReturnDefaultsBeforeLateCollect(): void
    {
        $collector = new AgentSkillsDataCollector([]);

        $this->assertSame([], $collector->getSkills());
        $this->assertSame([], $collector->getCalls());
        $this->assertSame(0, $collector->getTotalCalls());
        $this->assertSame(0, $collector->getTotalSkills());
    }

    public function testLateCollectAggregatesFromSingleLoader(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('code-review');
        $skill->method('getDescription')->willReturn('Review code');

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->willReturn($skill);
        $inner->method('loadSkills')->willReturn(['code-review' => $skill]);

        $loader = new TraceableSkillLoader($inner, new MockClock());
        $loader->loadSkill('code-review');
        $loader->loadSkills();

        $collector = new AgentSkillsDataCollector([$loader]);
        $collector->lateCollect();

        $this->assertSame(1, $collector->getTotalSkills());
        $this->assertSame(2, $collector->getTotalCalls());
        $this->assertArrayHasKey('code-review', $collector->getSkills());
        $this->assertSame('code-review', $collector->getSkills()['code-review']['name']);
        $this->assertSame('Review code', $collector->getSkills()['code-review']['description']);
    }

    public function testLateCollectAggregatesFromMultipleLoaders(): void
    {
        $skill1 = $this->createMock(SkillInterface::class);
        $skill1->method('getName')->willReturn('code-review');
        $skill1->method('getDescription')->willReturn('Review code');

        $skill2 = $this->createMock(SkillInterface::class);
        $skill2->method('getName')->willReturn('twig-component');
        $skill2->method('getDescription')->willReturn('Build Twig components');

        $inner1 = $this->createMock(SkillLoaderInterface::class);
        $inner1->method('loadSkill')->willReturn($skill1);

        $inner2 = $this->createMock(SkillLoaderInterface::class);
        $inner2->method('loadSkill')->willReturn($skill2);

        $loader1 = new TraceableSkillLoader($inner1, new MockClock());
        $loader1->loadSkill('code-review');

        $loader2 = new TraceableSkillLoader($inner2, new MockClock());
        $loader2->loadSkill('twig-component');

        $collector = new AgentSkillsDataCollector([$loader1, $loader2]);
        $collector->lateCollect();

        $this->assertSame(2, $collector->getTotalSkills());
        $this->assertSame(2, $collector->getTotalCalls());
        $this->assertArrayHasKey('code-review', $collector->getSkills());
        $this->assertArrayHasKey('twig-component', $collector->getSkills());
    }

    public function testLateCollectDeduplicatesSkillsByName(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('code-review');
        $skill->method('getDescription')->willReturn('Review code');

        $inner1 = $this->createMock(SkillLoaderInterface::class);
        $inner1->method('loadSkill')->willReturn($skill);

        $inner2 = $this->createMock(SkillLoaderInterface::class);
        $inner2->method('loadSkill')->willReturn($skill);

        $loader1 = new TraceableSkillLoader($inner1, new MockClock());
        $loader1->loadSkill('code-review');

        $loader2 = new TraceableSkillLoader($inner2, new MockClock());
        $loader2->loadSkill('code-review');

        $collector = new AgentSkillsDataCollector([$loader1, $loader2]);
        $collector->lateCollect();

        $this->assertSame(1, $collector->getTotalSkills());
        $this->assertSame(2, $collector->getTotalCalls());
    }

    public function testResetClearsDataAndResetsLoaders(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('code-review');
        $skill->method('getDescription')->willReturn('Review code');

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->willReturn($skill);

        $loader = new TraceableSkillLoader($inner, new MockClock());
        $loader->loadSkill('code-review');

        $collector = new AgentSkillsDataCollector([$loader]);
        $collector->lateCollect();

        $this->assertSame(1, $collector->getTotalSkills());

        $collector->reset();

        $this->assertSame([], $collector->getSkills());
        $this->assertSame(0, $collector->getTotalCalls());
        $this->assertSame([], $loader->calls);
    }
}
