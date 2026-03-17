<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Symfony\AI\Profiler;

use AgentSkills\Bridge\Symfony\AI\Profiler\TraceableSkillLoader;
use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\SkillMetadataInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TraceableSkillLoaderTest extends TestCase
{
    public function testLoadSkillDelegatesAndRecords(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('code-review');

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->with('code-review')->willReturn($skill);

        $clock = new MockClock();
        $loader = new TraceableSkillLoader($inner, $clock);

        $result = $loader->loadSkill('code-review');

        $this->assertSame($skill, $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('loadSkill', $loader->calls[0]['method']);
        $this->assertArrayHasKey('skill', $loader->calls[0]);
        $this->assertSame($skill, $loader->calls[0]['skill']);
    }

    public function testLoadSkillRecordsNullWhenNotFound(): void
    {
        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->with('missing')->willReturn(null);

        $loader = new TraceableSkillLoader($inner, new MockClock());

        $result = $loader->loadSkill('missing');

        $this->assertNull($result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('loadSkill', $loader->calls[0]['method']);
        $this->assertArrayHasKey('skill', $loader->calls[0]);
        $this->assertNull($loader->calls[0]['skill']);
    }

    public function testLoadSkillsDelegatesAndRecords(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('twig-component');

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkills')->willReturn(['twig-component' => $skill]);

        $clock = new MockClock();
        $loader = new TraceableSkillLoader($inner, $clock);

        $result = $loader->loadSkills();

        $this->assertSame(['twig-component' => $skill], $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('loadSkills', $loader->calls[0]['method']);
        $this->assertArrayHasKey('skills', $loader->calls[0]);
        $this->assertSame(['twig-component' => $skill], $loader->calls[0]['skills']);
    }

    public function testDiscoverMetadataDelegatesAndRecords(): void
    {
        $metadata = $this->createMock(SkillMetadataInterface::class);

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('discoverMetadata')->willReturn(['my-skill' => $metadata]);

        $clock = new MockClock();
        $loader = new TraceableSkillLoader($inner, $clock);

        $result = $loader->discoverMetadata();

        $this->assertSame(['my-skill' => $metadata], $result);
        $this->assertCount(1, $loader->calls);
        $this->assertSame('discoverMetadata', $loader->calls[0]['method']);
        $this->assertArrayHasKey('metadata', $loader->calls[0]);
        $this->assertSame(['my-skill' => $metadata], $loader->calls[0]['metadata']);
    }

    public function testResetClearsCalls(): void
    {
        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->willReturn(null);

        $loader = new TraceableSkillLoader($inner, new MockClock());
        $loader->loadSkill('foo');
        $loader->loadSkill('bar');

        $this->assertCount(2, $loader->calls);

        $loader->reset();

        $this->assertSame([], $loader->calls);
    }

    public function testMultipleCallsAccumulate(): void
    {
        $skill = $this->createMock(SkillInterface::class);
        $skill->method('getName')->willReturn('code-review');

        $metadata = $this->createMock(SkillMetadataInterface::class);

        $inner = $this->createMock(SkillLoaderInterface::class);
        $inner->method('loadSkill')->willReturn($skill);
        $inner->method('loadSkills')->willReturn(['code-review' => $skill]);
        $inner->method('discoverMetadata')->willReturn(['code-review' => $metadata]);

        $loader = new TraceableSkillLoader($inner, new MockClock());

        $loader->loadSkill('code-review');
        $loader->loadSkills();
        $loader->discoverMetadata();

        $this->assertCount(3, $loader->calls);
        $this->assertSame('loadSkill', $loader->calls[0]['method']);
        $this->assertSame('loadSkills', $loader->calls[1]['method']);
        $this->assertSame('discoverMetadata', $loader->calls[2]['method']);
    }
}
