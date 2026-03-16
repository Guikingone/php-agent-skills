## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

## Installation

```bash
composer require guikingone/agent-skills
```

## Quick start - Standalone

```php
require_once dirname(__DIR__) . '/vendor/autoload.php';

$loader = new FilesystemSkillLoader([
    __DIR__ . '/.skills',
]);

$skill = $loader->loadSkill('twig-component');

assert($skill instanceof SkillInterface);

# Agent call with the loaded skill
```

## Quick start - Symfony

If symfony/flex is not installed, manually update the `config/bundles.php`:

```php
// config/bundles.php

return [
    // ...
    AgentSkills\Bridge\Symfony\AI\AgentSkillsBundle::class => ['all' => true],
];
```

Then configure skills in `config/packages/agent_skills.yaml`:

```yaml
# config/packages/agent_skills.yaml
agent_skills:
    skills:
        enabled: true
        agent: 'ai.agent.agent_with_skills'
        directories:
            - '%kernel.project_dir%/skills'
            - '%kernel.project_dir%/vendor/my-org/shared-skills'
        active_skills:
            - 'twig-component'
            - 'symfony-console'
        include_index: true
```

## Quick start - Laravel

Install the package alongside `laravel/ai`:

```bash
composer require guikingone/agent-skills laravel/ai
```

The `AgentSkillsServiceProvider` is auto-discovered by Laravel. If auto-discovery is disabled,
register it manually in `bootstrap/providers.php`:

```php
return [
    // ...
    AgentSkills\Bridge\Laravel\AI\AgentSkillsServiceProvider::class,
];
```

Publish and configure `config/agent-skills.php`:

```bash
php artisan vendor:publish --tag=agent-skills-config
```

```php
// config/agent-skills.php
return [
    'skills' => [
        'enabled' => true,
        'agent' => \App\Agents\MyAgent::class,
        'directories' => [
            resource_path('skills'),
        ],
        'active_skills' => [
            'twig-component',
            'symfony-console',
        ],
        'include_index' => true,
    ],
];
```
