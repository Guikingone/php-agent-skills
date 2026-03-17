<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Tool;

use AgentSkills\Bridge\Laravel\AI\Tool\ExecuteSkillScriptTool;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function chmod;
use function random_bytes;
use function sys_get_temp_dir;

final class ExecuteSkillScriptToolTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/exec_script_tool_test_' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testHandleExecutesPhpScript(): void
    {
        $this->createSkillWithScript('script-skill', 'test.php', '<?php echo "Hello from PHP";');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new ExecuteSkillScriptTool($loader, 'script-skill');

        $result = $tool->handle(new Request(['script' => 'test.php']));

        $this->assertStringContainsString('# Script execution: test.php', $result);
        $this->assertStringContainsString('Hello from PHP', $result);
    }

    public function testHandleReturnsErrorForMissingSkill(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new ExecuteSkillScriptTool($loader, 'nonexistent');

        $result = $tool->handle(new Request(['script' => 'test.sh']));

        $this->assertStringContainsString('Skill "nonexistent" not found.', $result);
    }

    public function testHandleReturnsErrorForMissingScript(): void
    {
        $this->createSkill('no-script-skill', 'A skill without scripts', 'Body.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new ExecuteSkillScriptTool($loader, 'no-script-skill');

        $result = $tool->handle(new Request(['script' => 'missing.sh']));

        $this->assertStringContainsString('Error loading script "missing.sh"', $result);
    }

    public function testHandleExecutesShellScript(): void
    {
        $this->createSkillWithScript('sh-skill', 'hello.sh', '#!/bin/bash' . "\n" . 'echo "Hello from Bash"');
        chmod($this->tempDir . '/sh-skill/scripts/hello.sh', 0o755);

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new ExecuteSkillScriptTool($loader, 'sh-skill');

        $result = $tool->handle(new Request(['script' => 'hello.sh']));

        $this->assertStringContainsString('Hello from Bash', $result);
    }

    public function testDescriptionIncludesSkillName(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $tool = new ExecuteSkillScriptTool($loader, 'my-skill');

        $this->assertStringContainsString('my-skill', $tool->description());
    }

    private function createSkill(string $name, string $description, string $body): void
    {
        $fs = new Filesystem();
        $skillDir = $this->tempDir . '/' . $name;
        $fs->mkdir($skillDir);
        $fs->dumpFile($skillDir . '/SKILL.md', "---\nname: {$name}\ndescription: {$description}\n---\n{$body}");
    }

    private function createSkillWithScript(string $skillName, string $scriptName, string $scriptContent): void
    {
        $this->createSkill($skillName, 'A skill with scripts', 'Body.');

        $fs = new Filesystem();
        $scriptsDir = $this->tempDir . '/' . $skillName . '/scripts';
        $fs->mkdir($scriptsDir);
        $fs->dumpFile($scriptsDir . '/' . $scriptName, $scriptContent);
    }
}
