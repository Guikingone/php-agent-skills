<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\NeuronAI\Tool;

use AgentSkills\Bridge\NeuronAI\Tool\SkillToolFactory;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

final class SkillToolFactoryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/neuron_tool_test_' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testCreateGetSkillToolReturnsToolWithCorrectNameAndDescription(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillTool('my-skill');

        $this->assertSame('get_skill_my-skill', $tool->getName());
        $this->assertStringContainsString('my-skill', (string) $tool->getDescription());
    }

    public function testCreateGetSkillToolExecutionReturnsSkillContent(): void
    {
        $this->createSkill('my-skill', 'A useful skill', 'Do something useful.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillTool('my-skill');
        $tool->setInputs([]);
        $tool->execute();

        $result = $tool->getResult();
        $this->assertStringContainsString('# Skill: my-skill', $result);
        $this->assertStringContainsString('Do something useful.', $result);
    }

    public function testCreateGetSkillToolExecutionReturnsNotFoundForMissingSkill(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillTool('nonexistent');
        $tool->setInputs([]);
        $tool->execute();

        $this->assertStringContainsString('Skill "nonexistent" not found.', $tool->getResult());
    }

    public function testCreateGetSkillToolExecutionWithReferenceIncludesReferenceContent(): void
    {
        $this->createSkill('ref-skill', 'A skill with references', 'Body.');

        $fs = new Filesystem();
        $fs->mkdir($this->tempDir . '/ref-skill/references');
        $fs->dumpFile($this->tempDir . '/ref-skill/references/guide.md', 'Reference content here');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillTool('ref-skill');
        $tool->setInputs(['reference' => 'guide.md']);
        $tool->execute();

        $result = $tool->getResult();
        $this->assertStringContainsString('## Reference: guide.md', $result);
        $this->assertStringContainsString('Reference content here', $result);
    }

    public function testCreateGetSkillsToolReturnsAllSkills(): void
    {
        $this->createSkill('skill-a', 'Skill A', 'Body A.');
        $this->createSkill('skill-b', 'Skill B', 'Body B.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillsTool();

        $this->assertSame('get_skills', $tool->getName());

        $tool->setInputs([]);
        $tool->execute();

        $result = $tool->getResult();
        $this->assertStringContainsString('# Skill: skill-a', $result);
        $this->assertStringContainsString('# Skill: skill-b', $result);
    }

    public function testCreateGetSkillsToolReturnsNoSkillsMessage(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createGetSkillsTool();
        $tool->setInputs([]);
        $tool->execute();

        $this->assertSame('No skills available.', $tool->getResult());
    }

    public function testCreateExecuteSkillScriptToolReturnsToolWithCorrectNameAndDescription(): void
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createExecuteSkillScriptTool('my-skill');

        $this->assertSame('execute_skill_script_my-skill', $tool->getName());
        $this->assertStringContainsString('my-skill', (string) $tool->getDescription());
    }

    public function testCreateExecuteSkillScriptToolExecutesScript(): void
    {
        $this->createSkill('script-skill', 'A skill with scripts', 'Body.');

        $fs = new Filesystem();
        $fs->mkdir($this->tempDir . '/script-skill/scripts');
        $fs->dumpFile($this->tempDir . '/script-skill/scripts/hello.sh', '#!/bin/bash' . "\n" . 'echo "Hello from script"');
        $fs->chmod($this->tempDir . '/script-skill/scripts/hello.sh', 0o755);

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $factory = new SkillToolFactory($loader);

        $tool = $factory->createExecuteSkillScriptTool('script-skill');
        $tool->setInputs(['script' => 'hello.sh']);
        $tool->execute();

        $result = $tool->getResult();
        $this->assertStringContainsString('Script execution: hello.sh', $result);
        $this->assertStringContainsString('Hello from script', $result);
    }

    private function createSkill(string $name, string $description, string $body): void
    {
        $fs = new Filesystem();
        $skillDir = $this->tempDir . '/' . $name;
        $fs->mkdir($skillDir);
        $fs->dumpFile($skillDir . '/SKILL.md', "---\nname: {$name}\ndescription: {$description}\n---\n{$body}");
    }
}
