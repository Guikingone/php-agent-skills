# CLAUDE.md

## Project overview

**php-agent-skills** (`guikingone/agent-skills`) is a PHP library implementing the [Agent Skills specification](https://agentskills.io/specification). It provides a framework-agnostic core for parsing, loading, validating, and evaluating SKILL.md-based agent skills, with an optional Symfony AI bridge.

## Architecture

### Core library (`src/` excluding `src/Bridge/`)

Framework-agnostic. Defines interfaces and implementations for:

- **Skill parsing**: `SkillParserInterface` / `SkillParser` — YAML frontmatter + Markdown body from SKILL.md files
- **Skill loading**: `SkillLoaderInterface` with three implementations:
  - `FilesystemSkillLoader` — local directories via Symfony Finder
  - `GithubSkillLoader` — GitHub Contents API (public/private repos, branches, tokens)
  - `ChainSkillLoader` — composes multiple loaders, first-match-wins for individual skills
- **Validation**: `SkillValidatorInterface` / `SkillValidator` — checks spec compliance (kebab-case name, required fields)
- **Evaluation** (`src/Evaluation/`): `AgentExecutorInterface`, `LlmClientInterface`, `EvalRunner`, `LlmGrader`, `BenchmarkAggregator`, `WorkspaceManager`

### Symfony AI Bridge (`src/Bridge/Symfony/AI/`)

Optional integration with `symfony/ai-agent`. Provides:

- `SkillTool` — exposes skills as Symfony AI tools via `#[AsTool]` attributes
- `SkillInputProcessor` — implements `InputProcessorInterface`, injects skills into system prompt
- `SymfonyAgentExecutor` — adapts `AgentInterface` to `AgentExecutorInterface`
- `SymfonyLlmClient` — adapts `PlatformInterface` to `LlmClientInterface`
- `AgentSkillBundleExtension` / `AgentSkillsBundleConfiguration` — Symfony DI bundle configuration
- Console commands: `ai:agent:validate-skills`, `ai:agent:eval-skill`

### Key design decisions

- Core must **never** import from `Symfony\AI\*` — those live exclusively in `src/Bridge/Symfony/AI/`
- Skills use **closure-based resource loading** for source-agnostic parsing (filesystem or GitHub content)
- Two discovery levels: Level 1 = metadata only (`discoverMetadata()`), Level 2 = full content (`loadSkill()`)
- When an `agent` identifier is set in bundle configuration, skills are auto-registered as tools — no separate `tools.enabled` flag

## Code style and conventions

### Mandatory for every PHP file

- `declare(strict_types=1);` at the top of every file
- PSR-4 autoloading: `AgentSkills\` → `src/`, `AgentSkills\Tests\` → `tests/`
- **Yoda-style** comparisons: `null !== $value`, `[] === $array`, `'string' === $var`
- **Static lambdas**: `static fn () => ...` (no `$this` binding)
- **Trailing commas** in multiline arrays, arguments, and parameters
- **Import all symbols** at file level: classes, functions (`use function sprintf;`), constants (`use const PHP_BINARY;`)
- **No FQCN in code**: use `sprintf()` not `\sprintf()`, use `RuntimeException` not `\RuntimeException`
- `concat_space`: `'a' . 'b'` (with spaces around `.`)

### Formatting rules (enforced by PHP-CS-Fixer)

- `@Symfony` + `@Symfony:risky` + `@PHP82Migration` rulesets
- `phpdoc_align`: left-aligned
- `native_function_invocation`: internal functions are imported and called without backslash
- `strict_comparison` + `strict_param`: no loose comparisons or `in_array` without strict
- `no_superfluous_phpdoc_tags`: remove unnecessary `@param`/`@return` when type-hinted

### Test file naming and location

- Tests mirror the `src/` structure under `tests/`
- Bridge tests: `tests/Bridge/Symfony/AI/`
- Test class suffix: `Test` (e.g., `SkillParserTest`)
- Use PHPUnit 11.x with `TestCase`

## Commands

```bash
# Run tests
php vendor/bin/phpunit

# Fix code style
php vendor/bin/php-cs-fixer fix

# Check code style (dry-run)
php vendor/bin/php-cs-fixer fix --dry-run --diff

# Static analysis
php vendor/bin/phpstan analyze

# Regenerate autoloader (required after adding/moving files due to classmap-authoritative)
composer dump-autoload
```

## Important: autoloader

`composer.json` has `classmap-authoritative: true`. After creating, moving, or deleting PHP files, **always run** `symfony composer dump-autoload` before running tests — otherwise new classes won't be found.

## CI pipeline

Three GitHub Actions workflows on `main` branch (push, PR, daily cron):

- **PHPUnit**: PHP 8.2–8.5, highest/lowest dependencies (PHP 8.2 uses `--ignore-platform-reqs`)
- **PHP-CS-Fixer**: same matrix, `--dry-run` mode
- **PHPStan**: same matrix, level 6, per-PHP-version config file (`phpstan.neon.{version}.dist`)

## Dependencies

### Runtime (`require`)

- PHP >= 8.2
- `symfony/clock`, `symfony/filesystem`, `symfony/finder`, `symfony/http-client`, `symfony/string` (^7.3|^8.0)

### Dev-only (`require-dev`)

- `symfony/ai-agent` ^0.6.0 — needed only for Bridge compilation and tests
- `symfony/config`, `symfony/console`, `symfony/dependency-injection` — Symfony bundle testing
- `phpunit/phpunit` ^11.5, `phpstan/phpstan` ^2.1, `php-cs-fixer` ^3.94, `rector/rector` ^2.3

## Documentation

- `doc/usage.md` — complete user guide (loaders, validation, evaluation, GitHub integration)
- `doc/bundle.md` — Symfony bundle configuration reference
- `doc/commands.md` — console commands reference (`ai:agent:eval-skill`, `ai:agent:validate-skills`)
- `examples/` — runnable examples with sample skills in `examples/.skills/`
