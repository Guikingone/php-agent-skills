## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

```bash
composer require guikingone/agent-skills
```

## Quick start - Standalone

```php

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
