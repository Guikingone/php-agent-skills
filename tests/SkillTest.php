<?php

declare(strict_types=1);

namespace AgentSkills\Tests;

use AgentSkills\Skill;
use AgentSkills\SkillMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

final class SkillTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/skill_test_' . bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testGetMetadata(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('foo', $metadata);

        $this->assertSame($metadata, $skill->getMetadata());
    }

    public function testGetNameDelegatesToMetadata(): void
    {
        $skill = new Skill('foo', new SkillMetadata('my-skill', 'A skill'));

        $this->assertSame('my-skill', $skill->getName());
    }

    public function testGetDescriptionDelegatesToMetadata(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A useful skill');
        $skill = new Skill('foo', $metadata);

        $this->assertSame('A useful skill', $skill->getDescription());
    }

    public function testGetBody(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $this->assertSame('This is the instruction body.', $skill->getBody());
    }

    public function testLoadScriptReturnsPath(): void
    {
        $scriptPath = $this->tempDir . '/scripts/setup.sh';

        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('body', $metadata, scriptsLoader: static fn (string $script): string => $scriptPath);

        $this->assertSame($scriptPath, $skill->loadScript('setup.sh'));
    }

    public function testLoadReferenceReturnsContent(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('body', $metadata, referencesLoader: static fn (string $ref): string => '# API Reference');

        $this->assertSame('# API Reference', $skill->loadReference('api.md'));
    }

    public function testLoadAssetReturnsPath(): void
    {
        $assetPath = $this->tempDir . '/assets/logo.png';

        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('body', $metadata, assetsLoader: static fn (string $asset): string => $assetPath);

        $this->assertSame($assetPath, $skill->loadAsset('logo.png'));
    }
}
