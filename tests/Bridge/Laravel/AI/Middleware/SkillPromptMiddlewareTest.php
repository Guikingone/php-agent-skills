<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Bridge\Laravel\AI\Middleware;

use AgentSkills\Bridge\Laravel\AI\Middleware\SkillPromptMiddleware;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillParser;
use AgentSkills\Validation\SkillValidator;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function bin2hex;
use function random_bytes;
use function sys_get_temp_dir;

final class SkillPromptMiddlewareTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/skill_middleware_test_' . bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    public function testMiddlewareDoesNothingWhenNoSkills()
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, [], false);

        $prompt = $this->createPrompt('Original prompt');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertSame('Original prompt', $passedPrompt->prompt);
    }

    public function testMiddlewareIncludesSkillIndex()
    {
        $this->createSkill('test-skill', 'A test skill for indexing');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, [], true);

        $prompt = $this->createPrompt('User question');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertStringContainsString('## Available Skills', $passedPrompt->prompt);
        $this->assertStringContainsString('test-skill', $passedPrompt->prompt);
        $this->assertStringContainsString('A test skill for indexing', $passedPrompt->prompt);
    }

    public function testMiddlewareSkipsIndexWhenDisabled()
    {
        $this->createSkill('indexed-skill', 'Should not appear in index');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, ['indexed-skill'], false);

        $prompt = $this->createPrompt('User question');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertStringNotContainsString('## Available Skills', $passedPrompt->prompt);
        $this->assertStringContainsString('## Skill: indexed-skill', $passedPrompt->prompt);
    }

    public function testMiddlewareLoadsActiveSkillsFully()
    {
        $this->createSkill('active-skill', 'An active skill', 'Full body content here.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, ['active-skill'], false);

        $prompt = $this->createPrompt('User question');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertStringContainsString('## Skill: active-skill', $passedPrompt->prompt);
        $this->assertStringContainsString('Full body content here.', $passedPrompt->prompt);
    }

    public function testMiddlewareIgnoresMissingActiveSkills()
    {
        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, ['nonexistent-skill'], false);

        $prompt = $this->createPrompt('User question');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertSame('User question', $passedPrompt->prompt);
    }

    public function testMiddlewareCombinesIndexAndActiveSkills()
    {
        $this->createSkill('index-skill', 'Appears in index');
        $this->createSkill('active-skill', 'Loaded fully', 'Active body.');

        $loader = new FilesystemSkillLoader([$this->tempDir], new SkillParser(), new SkillValidator());
        $middleware = new SkillPromptMiddleware($loader, ['active-skill'], true);

        $prompt = $this->createPrompt('User question');
        $passedPrompt = null;

        $middleware->handle($prompt, static function (AgentPrompt $p) use (&$passedPrompt) {
            $passedPrompt = $p;

            return 'result';
        });

        $this->assertStringContainsString('## Available Skills', $passedPrompt->prompt);
        $this->assertStringContainsString('## Skill: active-skill', $passedPrompt->prompt);
        $this->assertStringContainsString('Active body.', $passedPrompt->prompt);
    }

    private function createPrompt(string $text): AgentPrompt
    {
        $agent = $this->createMock(Agent::class);
        $provider = $this->createMock(TextProvider::class);

        return new AgentPrompt($agent, $text, [], $provider, 'test-model');
    }

    private function createSkill(string $name, string $description, string $body = 'Body.'): void
    {
        $fs = new Filesystem();
        $skillDir = $this->tempDir . '/' . $name;
        $fs->mkdir($skillDir);
        $fs->dumpFile($skillDir . '/SKILL.md', "---\nname: {$name}\ndescription: {$description}\n---\n{$body}");
    }
}
