<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Tool;

use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillsTool;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

final class GetSkillsToolTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/get_skills_tool_test_' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testHandleReturnsAllSkills(): void
    {
        $this->createSkill('skill-one', 'First skill', 'First body.');
        $this->createSkill('skill-two', 'Second skill', 'Second body.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillsTool($loader);

        $result = $tool->handle(new Request());

        $this->assertStringContainsString('# Skill: skill-one', $result);
        $this->assertStringContainsString('# Skill: skill-two', $result);
        $this->assertStringContainsString('First body.', $result);
        $this->assertStringContainsString('Second body.', $result);
    }

    public function testHandleReturnsMessageWhenNoSkills(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillsTool($loader);

        $result = $tool->handle(new Request());

        $this->assertSame('No skills available.', $result);
    }

    public function testDescriptionReturnsExpectedString(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillsTool($loader);

        $this->assertSame('Get all available skills', $tool->description());
    }

    private function createSkill(string $name, string $description, string $body): void
    {
        $fs = new Filesystem();
        $skillDir = $this->tempDir . '/' . $name;
        $fs->mkdir($skillDir);
        $fs->dumpFile($skillDir . '/SKILL.md', "---\nname: {$name}\ndescription: {$description}\n---\n{$body}");
    }
}
