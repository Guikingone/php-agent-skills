<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Tool;

use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillTool;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

final class GetSkillToolTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/get_skill_tool_test_' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testHandleReturnsSkillContent()
    {
        $this->createSkill('my-skill', 'A useful skill', 'Do something useful.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillTool($loader, 'my-skill');

        $result = $tool->handle(new Request());

        $this->assertStringContainsString('# Skill: my-skill', $result);
        $this->assertStringContainsString('Do something useful.', $result);
    }

    public function testHandleReturnsNotFoundForMissingSkill()
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillTool($loader, 'nonexistent');

        $result = $tool->handle(new Request());

        $this->assertStringContainsString('Skill "nonexistent" not found.', $result);
    }

    public function testHandleWithReferenceIncludesReferenceContent()
    {
        $this->createSkill('ref-skill', 'A skill with references', 'Body.');

        $fs = new Filesystem();
        $fs->mkdir($this->tempDir . '/ref-skill/references');
        $fs->dumpFile($this->tempDir . '/ref-skill/references/guide.md', 'Reference content here');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillTool($loader, 'ref-skill');

        $result = $tool->handle(new Request(['reference' => 'guide.md']));

        $this->assertStringContainsString('## Reference: guide.md', $result);
        $this->assertStringContainsString('Reference content here', $result);
    }

    public function testDescriptionIncludesSkillName()
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new GetSkillTool($loader, 'my-skill');

        $this->assertStringContainsString('my-skill', $tool->description());
    }

    private function createSkill(string $name, string $description, string $body): void
    {
        $fs = new Filesystem();
        $skillDir = $this->tempDir . '/' . $name;
        $fs->mkdir($skillDir);
        $fs->dumpFile($skillDir . '/SKILL.md', "---\nname: {$name}\ndescription: {$description}\n---\n{$body}");
    }
}
