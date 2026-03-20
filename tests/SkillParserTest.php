<?php

declare(strict_types=1);

namespace AgentSkills\Tests;

use AgentSkills\Exception\InvalidArgumentException;
use AgentSkills\Skill;
use AgentSkills\SkillMetadata;
use AgentSkills\SkillParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function array_map;
use function bin2hex;
use function explode;
use function implode;
use function ltrim;
use function min;
use function preg_match;
use function random_bytes;
use function sprintf;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function trim;

use const PHP_INT_MAX;

final class SkillParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/skill_parser_test_' . bin2hex(random_bytes(4));

        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testEmptyNameField(): void
    {
        $this->createSkillFile("---\nname: \"\"\ndescription: A skill with empty name\n---\nBody.");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Missing or invalid required field "name" in "%s/SKILL.md".', $this->tempDir));
        $this->expectExceptionCode(0);
        (new SkillParser())->parse($this->tempDir);
    }

    public function testInvalidKebabCaseName(): void
    {
        $this->createSkillFile("---\nname: My_Skill\ndescription: A skill with bad name format\n---\nBody.");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Skill name "My_Skill" must be non-empty kebab-case (e.g. "my-skill")');
        $this->expectExceptionCode(0);
        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseMinimalSkill(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: my-skill
            description: A simple skill
            ---
            Do something useful.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertInstanceOf(Skill::class, $skill);
        $this->assertSame('my-skill', $skill->getName());
        $this->assertSame('A simple skill', $skill->getDescription());
        $this->assertSame('Do something useful.', $skill->getBody());
    }

    public function testParseSkillWithAllFrontmatterFields(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: pdf-processing
            description: Processes PDF documents and extracts content
            license: MIT
            allowed-tools: Read Write Bash
            compatibility: claude >=3.5
            metadata:
              author: Symfony
              version: 1.0.0
            ---
            ## Instructions

            Extract text from PDF files.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('pdf-processing', $skill->getName());
        $this->assertSame('Processes PDF documents and extracts content', $skill->getDescription());

        $metadata = $skill->getMetadata();
        $this->assertSame('MIT', $metadata->getLicense());
        $this->assertSame(['Read', 'Write', 'Bash'], $metadata->getAllowedTools());
        $this->assertSame('claude >=3.5', $metadata->getCompatibility());
        $this->assertSame('Symfony', $metadata->getAuthor());
        $this->assertSame('1.0.0', $metadata->getVersion());
        $this->assertStringContainsString('Extract text from PDF files.', $skill->getBody());
    }

    public function testParseSkillWithEmptyBody(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: empty-body
            description: Skill with no body
            ---
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('empty-body', $skill->getName());
        $this->assertSame('', $skill->getBody());
    }

    public function testParseSkillWithMultilineBody(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: code-review
            description: Reviews code changes
            ---
            ## Step 1

            Analyze the code.

            ## Step 2

            Provide feedback.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertStringContainsString('## Step 1', $skill->getBody());
        $this->assertStringContainsString('## Step 2', $skill->getBody());
        $this->assertStringContainsString('Provide feedback.', $skill->getBody());
    }

    public function testParseThrowsWhenSkillMdIsMissing(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        (new Filesystem())->mkdir($emptyDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SKILL.md not found');

        (new SkillParser())->parse($emptyDir);
    }

    public function testParseThrowsWhenNoFrontmatter(): void
    {
        $this->createSkillFile('Just some markdown without frontmatter.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with YAML frontmatter');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseThrowsWhenFrontmatterNotClosed(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: broken
            description: Missing closing delimiter
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse YAML frontmatter');

        (new SkillParser())->parse($skillDir);
    }

    public function testParseThrowsWhenMissingName(): void
    {
        $this->createSkillFile(<<<'MD'
            ---
            description: A skill without a name
            ---
            Body content.
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid required field "name"');

        (new SkillParser())->parse($this->tempDir);
    }

    public function testParseThrowsWhenMissingDescription(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: no-desc
            ---
            Body content.
            MD);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing or invalid required field "description"');

        (new SkillParser())->parse($skillDir);
    }

    public function testParseMetadataOnlyReturnsSkillMetadata(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: lightweight
            description: Only metadata is parsed
            license: Apache-2.0
            ---
            This body should not matter for metadata-only parsing.
            MD);

        $metadata = (new SkillParser())->parseMetadataOnly($skillDir);

        $this->assertInstanceOf(SkillMetadata::class, $metadata);
        $this->assertSame('lightweight', $metadata->getName());
        $this->assertSame('Only metadata is parsed', $metadata->getDescription());
        $this->assertSame('Apache-2.0', $metadata->getLicense());
    }

    public function testParseMetadataOnlyThrowsWhenMissing(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        (new Filesystem())->mkdir($emptyDir);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SKILL.md not found');

        (new SkillParser())->parseMetadataOnly($emptyDir);
    }

    public function testParseSkillWithQuotedValues(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: quoted-values
            description: "A skill with quoted values"
            license: "MIT"
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('quoted-values', $skill->getName());
        $this->assertSame('A skill with quoted values', $skill->getDescription());
        $this->assertSame('MIT', $skill->getMetadata()->getLicense());
    }

    public function testParseSkillWithComments(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            # This is a comment
            name: commented
            description: Skill with YAML comments
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('commented', $skill->getName());
    }

    public function testParseSkillWithAllowedToolsSingleTool(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: single-tool
            description: Skill with one tool
            allowed-tools: Read
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame(['Read'], $skill->getMetadata()->getAllowedTools());
    }

    public function testParseThrowsWhenNameDoesNotMatchDirectory(): void
    {
        $mismatchDir = $this->tempDir . '/wrong-name';
        (new Filesystem())->mkdir($mismatchDir);
        (new Filesystem())->dumpFile($mismatchDir . '/SKILL.md', "---\nname: correct-name\ndescription: A skill in the wrong directory\n---\nBody.");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Skill name "correct-name" must match parent directory name "wrong-name"');

        (new SkillParser())->parse($mismatchDir);
    }

    public function testParseFromContentReturnsSkill(): void
    {
        $content = "---\nname: remote-skill\ndescription: A skill parsed from content\n---\nRemote instructions.";

        $skill = (new SkillParser())->parseFromContent($content, 'github://owner/repo/remote-skill');

        $this->assertSame('remote-skill', $skill->getName());
        $this->assertSame('A skill parsed from content', $skill->getDescription());
        $this->assertSame('Remote instructions.', $skill->getBody());
    }

    public function testParseFromContentWithCustomLoaders(): void
    {
        $content = "---\nname: remote-skill\ndescription: A skill with remote resources\n---\nBody.";

        $skill = (new SkillParser())->parseFromContent(
            $content,
            'github://owner/repo/remote-skill',
            static fn (string $script): string => '/tmp/scripts/' . $script,
            static fn (string $ref): string => 'Reference: ' . $ref,
            static fn (string $asset): string => 'Asset: ' . $asset,
        );

        $this->assertSame('/tmp/scripts/setup.sh', $skill->loadScript('setup.sh'));
        $this->assertSame('Reference: guide.md', $skill->loadReference('guide.md'));
        $this->assertSame('Asset: logo.png', $skill->loadAsset('logo.png'));
    }

    public function testParseMetadataFromContentReturnsMetadata(): void
    {
        $content = "---\nname: remote-skill\ndescription: Metadata only\nlicense: MIT\n---\nBody is ignored for metadata.";

        $metadata = (new SkillParser())->parseMetadataFromContent($content, 'github://owner/repo/remote-skill');

        $this->assertSame('remote-skill', $metadata->getName());
        $this->assertSame('Metadata only', $metadata->getDescription());
        $this->assertSame('MIT', $metadata->getLicense());
    }

    public function testLoadReferenceBuildsCorrectPath(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: ref-skill
            description: A skill with references
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($skillDir . '/references');
        (new Filesystem())->dumpFile($skillDir . '/references/guide.md', 'Reference content');

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('Reference content', $skill->loadReference('guide.md'));
    }

    public function testLoadAssetBuildsCorrectPath(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: asset-skill
            description: A skill with assets
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($skillDir . '/assets');
        (new Filesystem())->dumpFile($skillDir . '/assets/template.txt', 'Asset content');

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame('Asset content', $skill->loadAsset('template.txt'));
    }

    public function testParseCreatesScriptsLister(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: lister-skill
            description: A skill with scripts
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($skillDir . '/scripts');
        (new Filesystem())->dumpFile($skillDir . '/scripts/setup.sh', '#!/bin/bash');
        (new Filesystem())->dumpFile($skillDir . '/scripts/analyze.py', '# python');

        $skill = (new SkillParser())->parse($skillDir);

        $scripts = $skill->listScripts();
        $this->assertCount(2, $scripts);
        $this->assertContains('analyze.py', $scripts);
        $this->assertContains('setup.sh', $scripts);
    }

    public function testParseCreatesReferencesLister(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: ref-lister
            description: A skill with references
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($skillDir . '/references');
        (new Filesystem())->dumpFile($skillDir . '/references/guide.md', '# Guide');

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame(['guide.md'], $skill->listReferences());
    }

    public function testParseCreatesAssetsLister(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: asset-lister
            description: A skill with assets
            ---
            Body.
            MD);

        (new Filesystem())->mkdir($skillDir . '/assets');
        (new Filesystem())->dumpFile($skillDir . '/assets/logo.png', 'fake-png');
        (new Filesystem())->dumpFile($skillDir . '/assets/template.html', '<html>');

        $skill = (new SkillParser())->parse($skillDir);

        $assets = $skill->listAssets();
        $this->assertCount(2, $assets);
        $this->assertContains('logo.png', $assets);
        $this->assertContains('template.html', $assets);
    }

    public function testParseReturnsEmptyListWhenNoResourceDirectories(): void
    {
        $skillDir = $this->createSkillFile(<<<'MD'
            ---
            name: no-resources
            description: A skill without resource directories
            ---
            Body.
            MD);

        $skill = (new SkillParser())->parse($skillDir);

        $this->assertSame([], $skill->listScripts());
        $this->assertSame([], $skill->listReferences());
        $this->assertSame([], $skill->listAssets());
        $this->assertSame('', $skill->getResourceListing());
    }

    public function testParseFromContentWithCustomListers(): void
    {
        $content = "---\nname: remote-skill\ndescription: A skill with remote listers\n---\nBody.";

        $skill = (new SkillParser())->parseFromContent(
            $content,
            'github://owner/repo/remote-skill',
            scriptsLister: static fn (): array => ['deploy.sh'],
            referencesLister: static fn (): array => ['api.md'],
            assetsLister: static fn (): array => ['logo.svg'],
        );

        $this->assertSame(['deploy.sh'], $skill->listScripts());
        $this->assertSame(['api.md'], $skill->listReferences());
        $this->assertSame(['logo.svg'], $skill->listAssets());
        $this->assertStringContainsString('## Available Resources', $skill->getResourceListing());
    }

    /**
     * Creates a SKILL.md file in a subdirectory matching the skill name.
     *
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

        // Extract skill name to create a matching directory
        $skillDir = $this->tempDir;
        if (1 === preg_match('/^name:\s*["\']?([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)["\']?\s*$/m', $normalized, $matches)) {
            $skillDir = $this->tempDir . '/' . $matches[1];
        }

        (new Filesystem())->mkdir($skillDir);
        (new Filesystem())->dumpFile($skillDir . '/SKILL.md', $normalized);

        return $skillDir;
    }
}
