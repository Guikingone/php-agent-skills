# Skills Configuration

The Symfony integration provides built-in support for loading and using `Agent Skills`_ from both local
directories and remote GitHub repositories.

## Local Skills

Skills stored in local directories are loaded by the default filesystem loader:

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'
        directories:
            - '%kernel.project_dir%/skills'
            - '%kernel.project_dir%/vendor/my-org/shared-skills'
        active_skills:
            - 'twig-component'
            - 'symfony-console'
        include_index: true
```

## GitHub Skills

Skills can be loaded from GitHub repositories using the ``github_repositories`` option. When
configured alongside ``directories``, a ``AgentSkills\ChainSkillLoader``
is automatically created to transparently compose both loaders:

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'
        directories:
            - '%kernel.project_dir%/skills'
        github_repositories:
            # Public repository
            - repository: 'my-org/shared-skills'

            # Private repository with authentication
            - repository: 'my-org/private-skills'
              token: '%env(GITHUB_TOKEN)%'

            # Custom branch and subdirectory
            - repository: 'my-org/monorepo'
              path: 'ai/skills'
              branch: 'develop'
              token: '%env(GITHUB_TOKEN)%'
        active_skills:
            - 'twig-component'
```

Each repository entry supports:

* ``repository`` (required): GitHub repository in ``owner/repo`` format or full URL
* ``path`` (optional): Subdirectory where skills are stored (default: repository root)
* ``branch`` (optional): Branch to load from (default: ``main``)
* ``token`` (optional): Personal access token for private repositories

When both ``directories`` and ``github_repositories`` are configured, local skills take
precedence over GitHub skills with the same name.

## GitHub-Only Skills

To use only GitHub-based skills without local directories:

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'
        directories: []
        github_repositories:
            - repository: 'my-org/skills'
        active_skills:
            - 'my-skill'
```

## Skills as Tools

When an ``agent`` identifier is defined in the skills configuration, active skills are
automatically registered as callable tools for that agent. No separate ``tools`` flag is needed:

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'
        directories:
            - '%kernel.project_dir%/skills'
        github_repositories:
            - repository: 'my-org/skills'
        active_skills:
            - 'twig-component'
```

Each active skill is registered as a tool named ``skill_{name}`` (with dashes converted to
underscores). The agent can call these tools to load skill content, reference files, and
execute scripts on demand.

## Skill Evaluation

The evaluation system measures how well an agent performs with and without a skill. Configure
it under the ``evaluation`` key:

```yaml
agent_skills:
    skills:
        enabled: true
        agent: 'my_agent'
        directories:
            - '%kernel.project_dir%/skills'
        active_skills:
            - 'my-skill'
    evaluation:
        workspace: '%kernel.project_dir%/var/skill-evals'
        grading_model: 'gpt-4o-mini'
        grading_platform: 'ai.platform.openai'
```

Configuration options:

* ``workspace`` (string, default: ``%kernel.project_dir%/var/skill-evals``): Directory to store evaluation
  results (timing, grading, benchmark JSON files)
* ``grading_model`` (string, optional): Model to use for LLM-based assertion grading (e.g. ``gpt-4o-mini``)
* ``grading_platform`` (string, optional): Platform service reference for the grading model
  (e.g. ``ai.platform.openai``)

> **Note:** Both ``grading_model`` and ``grading_platform`` must be configured to enable LLM grading.
> Without them, the evaluation still runs but assertions are not graded.
