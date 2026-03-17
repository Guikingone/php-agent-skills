<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\NeuronAI;

use AgentSkills\Bridge\NeuronAI\SkillSystemPromptBuilder;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;

final class SkillSystemPromptBuilderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/neuron_prompt_test_' . bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testBuildReturnsNullWhenNoSkills(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, [], false);

        $this->assertNull($builder->build());
    }

    public function testBuildIncludesSkillIndex(): void
    {
        $this->createSkill('code-review', 'Reviews code changes');
        $this->createSkill('pdf-reader', 'Reads PDF documents');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, [], true);

        $result = $builder->build();

        $this->assertNotNull($result);
        $this->assertStringContainsString('# Agent Skills', $result);
        $this->assertStringContainsString('## Available Skills', $result);
        $this->assertStringContainsString('code-review', $result);
        $this->assertStringContainsString('pdf-reader', $result);
    }

    public function testBuildDoesNotIncludeIndexWhenDisabled(): void
    {
        $this->createSkill('my-skill', 'A skill');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, [], false);

        $this->assertNull($builder->build());
    }

    public function testBuildLoadsActiveSkillsFully(): void
    {
        $this->createSkill('code-review', 'Reviews code');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, ['code-review'], false);

        $result = $builder->build();

        $this->assertNotNull($result);
        $this->assertStringContainsString('## Skill: code-review', $result);
        $this->assertStringContainsString('Instructions for code-review.', $result);
    }

    public function testBuildIgnoresMissingActiveSkills(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, ['non-existent'], false);

        $this->assertNull($builder->build());
    }

    public function testBuildCombinesIndexAndActiveSkills(): void
    {
        $this->createSkill('skill-a', 'Skill A description');
        $this->createSkill('skill-b', 'Skill B description');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $builder = new SkillSystemPromptBuilder($loader, ['skill-a'], true);

        $result = $builder->build();

        $this->assertNotNull($result);
        $this->assertStringContainsString('## Available Skills', $result);
        $this->assertStringContainsString('skill-a', $result);
        $this->assertStringContainsString('skill-b', $result);
        $this->assertStringContainsString('## Skill: skill-a', $result);
        $this->assertStringContainsString('Instructions for skill-a.', $result);
    }

    private function createSkill(string $name, string $description): void
    {
        $skillDir = $this->tempDir . '/' . $name;

        (new Filesystem())->mkdir($skillDir);
        (new Filesystem())->dumpFile(
            $skillDir . '/SKILL.md',
            sprintf("---\nname: %s\ndescription: %s\n---\nInstructions for %s.", $name, $description, $name),
        );
    }
}
