# AGENTS.md

## Identity

You are working on **php-agent-skills** (`guikingone/agent-skills`), a PHP library that implements the [Agent Skills specification](https://agentskills.io/specification). This specification defines a standard format (SKILL.md files with YAML frontmatter + Markdown body) for giving AI agents new capabilities.

## Project structure

```
src/
├── Bridge/Symfony/AI/           # Symfony AI integration (optional)
│   ├── Command/                 # Console commands (eval-skill, validate-skills)
│   ├── DependencyInjection/     # Bundle extension + configuration
│   ├── Evaluation/              # Bridge adapters (SymfonyAgentExecutor, SymfonyLlmClient)
│   ├── SkillInputProcessor.php  # Injects skills into agent system prompt
│   └── SkillTool.php            # Skills as Symfony AI tools (#[AsTool])
├── Evaluation/                  # Evaluation framework (framework-agnostic)
│   ├── Aggregator/              # Benchmark statistics (mean, stddev)
│   ├── Grader/                  # LLM-based assertion grading
│   ├── Runner/                  # Eval execution (AgentExecutorInterface)
│   └── Workspace/               # Result persistence (JSON files)
├── Exception/                   # InvalidArgumentException, RuntimeException
├── Validation/                  # Skill validation against spec
├── ChainSkillLoader.php         # Composes multiple loaders
├── FilesystemSkillLoader.php    # Loads skills from local directories
├── GithubSkillLoader.php        # Loads skills from GitHub repositories
├── Skill.php                    # Core skill value object
├── SkillInterface.php           # Skill contract
├── SkillLoaderInterface.php     # Loader contract (loadSkill, loadSkills, discoverMetadata)
├── SkillMetadata.php            # Metadata value object
├── SkillMetadataInterface.php   # Metadata contract
├── SkillParser.php              # SKILL.md parser (frontmatter + body)
└── SkillParserInterface.php     # Parser contract
tests/                           # Mirrors src/ structure
doc/                             # usage.md, bundle.md, commands.md
examples/                        # Runnable examples with .skills/ directory
```

## Critical architectural rule

**The core library (`src/` minus `src/Bridge/`) must never depend on `Symfony\AI\*`.**

All framework-specific code lives in `src/Bridge/Symfony/AI/`. The core defines interfaces (`AgentExecutorInterface`, `LlmClientInterface`) that bridges implement. If you need to call an LLM platform or agent from core code, use the existing interfaces — never import Symfony AI directly.

## How to develop

### Setup

```bash
composer install
```

### Validation cycle

Always run all three before considering work complete:

```bash
# 1. Tests (146 tests currently)
php vendor/bin/phpunit

# 2. Code style
php vendor/bin/php-cs-fixer fix

# 3. Static analysis
php vendor/bin/phpstan analyze
```

### After creating, moving, or deleting files

The project uses `classmap-authoritative` in composer.json. You **must** regenerate the autoloader:

```bash
symfony composer dump-autoload
```

Failing to do this will cause "Class not found" errors in tests.

## Code conventions

### Every PHP file must have

```php
<?php

declare(strict_types=1);

namespace AgentSkills\...;

use RuntimeException;      // Import classes

use function sprintf;      // Import functions

use const PHP_BINARY;      // Import constants
```

### Style rules (enforced by PHP-CS-Fixer)

- **Yoda comparisons**: `null !== $value`, `[] === $array` (not `$value !== null`)
- **Static lambdas**: `static fn () => ...` (always `static` for closures that don't use `$this`)
- **Trailing commas**: in multiline arrays, function arguments, and parameters
- **No FQCN in code body**: write `sprintf()` not `\sprintf()`, `RuntimeException` not `\RuntimeException`
- **Concatenation**: `'a' . 'b'` (spaces around dot)
- **Strict comparisons only**: `===` / `!==`, `in_array($v, $arr, true)`
- **Left-aligned PHPDoc**: `@param` and `@return` tags aligned to the left
- **No redundant PHPDoc**: if the type is already in the signature, don't repeat it in `@param`/`@return`

### Test conventions

- Test files mirror `src/` structure under `tests/`
- Bridge tests go in `tests/Bridge/Symfony/AI/`
- Class name suffix: `Test` (e.g., `FilesystemSkillLoaderTest`)
- PHPUnit 11.x with `PHPUnit\Framework\TestCase`
- Use `self::assert*` methods, not `$this->assert*` (Symfony convention)

## Key interfaces to know

| Interface | Purpose | Implementations |
|-----------|---------|-----------------|
| `SkillLoaderInterface` | Load skills from any source | `FilesystemSkillLoader`, `GithubSkillLoader`, `ChainSkillLoader` |
| `SkillParserInterface` | Parse SKILL.md files | `SkillParser` |
| `SkillValidatorInterface` | Validate skills against spec | `SkillValidator` |
| `AgentExecutorInterface` | Execute prompts (framework-agnostic) | `SymfonyAgentExecutor` (Bridge) |
| `LlmClientInterface` | LLM platform calls | `SymfonyLlmClient` (Bridge) |
| `GraderInterface` | Grade eval assertions | `LlmGrader` |
| `WorkspaceManagerInterface` | Persist eval results | `WorkspaceManager` |

## Symfony bundle configuration

The bundle uses the `agent_skills` root key (not nested under `ai.agent`):

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'              # When set, skills are auto-registered as tools
        directories:
            - '%kernel.project_dir%/skills'
        github_repositories:
            - repository: 'owner/repo'
        active_skills:
            - 'my-skill'
        include_index: true
    evaluation:
        workspace: '%kernel.project_dir%/var/skill-evals'
        grading_model: 'gpt-4o-mini'
        grading_platform: 'ai.platform.openai'
```

When `agent` is set and `active_skills` are defined, each skill is automatically registered as a callable tool named `skill_{name}` (dashes converted to underscores). No separate `tools.enabled` flag exists.

## Agent Skills specification reference

The spec (https://agentskills.io/specification) defines:

- **SKILL.md structure**: YAML frontmatter (name, description, license, allowed-tools, compatibility, metadata) + Markdown body
- **Directory layout**: `SKILL.md` + optional `scripts/`, `references/`, `assets/`
- **Name format**: kebab-case (e.g., `twig-component`)
- **Two discovery levels**: Level 1 = metadata only (fast), Level 2 = full content with body + resource loaders

## Common tasks

### Adding a new skill loader

1. Create a class implementing `SkillLoaderInterface` in `src/`
2. Implement `loadSkill()`, `loadSkills()`, `discoverMetadata()`
3. Add tests in `tests/`
4. If Symfony DI integration is needed, register it in `AgentSkillBundleExtension`

### Adding a new Bridge

1. Create a new directory under `src/Bridge/` (e.g., `src/Bridge/Laravel/`)
2. Implement the core interfaces (`AgentExecutorInterface`, `LlmClientInterface`)
3. Add framework-specific integration (service providers, tools, etc.)
4. Never modify core interfaces to accommodate a specific framework

### Modifying the bundle configuration

1. Update `AgentSkillsBundleConfiguration.php` (TreeBuilder config tree)
2. Update `AgentSkillBundleExtension.php` (service registration logic)
3. Update `tests/Bridge/Symfony/AI/AgentSkillBundleExtensionTest.php`
4. Update `doc/bundle.md` with the new configuration options

## CI/CD

GitHub Actions run on push to `main`, PRs, and daily:

- **PHPUnit**: PHP 8.2–8.5, highest + lowest dependencies
- **PHP-CS-Fixer**: same matrix, dry-run mode
- **PHPStan**: level 6, per-PHP-version config files
