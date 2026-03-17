<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Validation;

use AgentSkills\Skill;
use AgentSkills\SkillMetadata;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function array_filter;
use function array_map;
use function bin2hex;
use function explode;
use function implode;
use function ltrim;
use function min;
use function preg_match;
use function random_bytes;
use function str_contains;
use function str_repeat;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;

use const PHP_INT_MAX;

final class SkillValidatorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/skill_validator_test_' . bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testValidateMinimalSkill(): void
    {
        $skillDir = $this->createSkillFile("---\nname: my-skill\ndescription: A useful skill for testing purposes\n---\nDo something useful.");

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    public function testValidateShortDescriptionWarning(): void
    {
        $skillDir = $this->createSkillFile("---\nname: short-desc\ndescription: Short\n---\nBody content here.");

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('short', $result->getWarnings()[0]);
    }

    public function testValidateUnknownFrontmatterFieldWarning(): void
    {
        $skillDir = $this->createSkillFile("---\nname: my-skill\ndescription: A properly described skill here\nunknown-field: value\n---\nBody.");

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Unknown frontmatter field "unknown-field"', $result->getWarnings()[0]);
    }

    public function testValidateEmptyBodyWarning(): void
    {
        $skillDir = $this->createSkillFile("---\nname: empty-body\ndescription: Skill with no body content at all\n---\n");

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());

        $bodyWarning = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'no body content'));

        $this->assertNotEmpty($bodyWarning);
    }

    public function testValidateFileSystemAllOptionalFields(): void
    {
        $content = <<<'MD'
            ---
            name: full-skill
            description: A complete skill with all optional fields populated
            license: MIT
            allowed-tools: Read Write Bash
            compatibility: claude >=3.5
            metadata:
              author: Symfony
              version: 1.0.0
            ---
            ## Instructions

            Do great things.
            MD;

        $skillDir = $this->createSkillFile($content);

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());

        $licenseWarnings = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'license'));
        $this->assertSame([], $licenseWarnings);
    }

    public function testValidateLicenseFieldIsRecognized(): void
    {
        $skillDir = $this->createSkillFile("---\nname: license-skill\ndescription: A properly described skill for testing purposes\nlicense: MIT\n---\nBody.");

        $skill = (new SkillParser())->parse($skillDir);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());

        $licenseWarnings = array_filter($result->getWarnings(), static fn (string $w): bool => str_contains($w, 'license'));
        $this->assertSame([], $licenseWarnings);
    }

    public function testValidateDescriptionTooLong(): void
    {
        $longDescription = str_repeat('a', 1025);
        $metadata = new SkillMetadata('my-skill', $longDescription);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('too long', $result->getErrors()[0]);
    }

    public function testValidateDescriptionAtExactLimit(): void
    {
        $exactDescription = str_repeat('a', 1024);
        $metadata = new SkillMetadata('my-skill', $exactDescription);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
    }

    public function testValidateCompatibilityTooLong(): void
    {
        $longCompatibility = str_repeat('a', 501);
        $metadata = new SkillMetadata('my-skill', 'A properly described skill for testing purposes', compatibility: $longCompatibility);
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('compatibility', $result->getErrors()[0]);
    }

    public function testValidateEmptyCompatibility(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A properly described skill for testing purposes', compatibility: '');
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('compatibility', $result->getErrors()[0]);
        $this->assertStringContainsString('non-empty', $result->getErrors()[0]);
    }

    public function testValidateSkillInterfaceWithShortDescription(): void
    {
        $metadata = new SkillMetadata('my-skill', 'Short');
        $skill = new Skill('Body content.', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('short', $result->getWarnings()[0]);
    }

    public function testValidateSkillInterfaceWithEmptyBody(): void
    {
        $metadata = new SkillMetadata('my-skill', 'A properly described skill for testing purposes');
        $skill = new Skill('', $metadata);

        $result = (new SkillValidator())->validate($skill);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('no body content', $result->getWarnings()[0]);
    }

    /**
     * @return string The skill directory path
     */
    private function createSkillFile(string $content): string
    {
        $lines = explode("\n", $content);
        $minIndent = PHP_INT_MAX;

        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $minIndent = min($minIndent, strlen($line) - strlen(ltrim($line)));
            }
        }

        if ($minIndent > 0 && $minIndent < PHP_INT_MAX) {
            $lines = array_map(static fn (string $l): string => strlen($l) >= $minIndent ? substr($l, $minIndent) : $l, $lines);
        }

        $normalized = implode("\n", $lines);

        $skillDir = $this->tempDir;
        if (1 === preg_match('/^name:\s*["\']?([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)["\']?\s*$/m', $normalized, $matches)) {
            $skillDir = $this->tempDir . '/' . $matches[1];
        }

        (new Filesystem())->mkdir($skillDir);
        (new Filesystem())->dumpFile($skillDir . '/SKILL.md', $normalized);

        return $skillDir;
    }
}
