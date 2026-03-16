# Laravel AI Integration

The Laravel AI integration provides built-in support for loading and using `Agent Skills`_ within
Laravel applications powered by ``laravel/ai``.

## Installation

Install the package alongside ``laravel/ai``:

```bash
composer require guikingone/agent-skills laravel/ai
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=agent-skills-config
```

This creates a ``config/agent-skills.php`` file in your application.

The ``AgentSkillsServiceProvider`` is auto-discovered by Laravel. If auto-discovery is disabled,
register it manually in ``bootstrap/providers.php``:

```php
return [
    // ...
    AgentSkills\Bridge\Laravel\AI\AgentSkillsServiceProvider::class,
];
```

## Configuration

The full configuration file (``config/agent-skills.php``):

```php
return [
    'skills' => [
        'enabled' => env('AGENT_SKILLS_ENABLED', false),
        'agent' => env('AGENT_SKILLS_AGENT'),
        'directories' => [
            resource_path('skills'),
        ],
        'github_repositories' => [],
        'active_skills' => [],
        'include_index' => false,
    ],
    'evaluation' => [
        'workspace' => storage_path('app/skill-evals'),
        'grading_model' => env('AGENT_SKILLS_GRADING_MODEL'),
        'grading_provider' => env('AGENT_SKILLS_GRADING_PROVIDER'),
    ],
];
```

Configuration options:

* ``enabled`` (bool, default: ``false``): Enable or disable the skills integration
* ``agent`` (string, optional): Agent class name for tool registration and eval commands
* ``directories`` (array): Local directories to scan for skills
* ``github_repositories`` (array): GitHub repositories to load skills from
* ``active_skills`` (array): Skill names to fully load and register as tools
* ``include_index`` (bool, default: ``false``): Include a skill metadata index in prompts
* ``workspace`` (string): Directory to store evaluation results
* ``grading_model`` (string, optional): Model for LLM-based assertion grading
* ``grading_provider`` (string, optional): Provider name for the grading model

## Local Skills

Skills stored in local directories are loaded by the filesystem loader:

```php
// config/agent-skills.php
'skills' => [
    'enabled' => true,
    'agent' => \App\Agents\MyAgent::class,
    'directories' => [
        resource_path('skills'),
        base_path('vendor/my-org/shared-skills'),
    ],
    'active_skills' => [
        'twig-component',
        'laravel-console',
    ],
    'include_index' => true,
],
```

## GitHub Skills

Skills can be loaded from GitHub repositories using the ``github_repositories`` option. When
configured alongside ``directories``, a ``AgentSkills\ChainSkillLoader``
is automatically created to transparently compose both loaders:

```php
// config/agent-skills.php
'skills' => [
    'enabled' => true,
    'agent' => \App\Agents\MyAgent::class,
    'directories' => [
        resource_path('skills'),
    ],
    'github_repositories' => [
        // Public repository
        ['repository' => 'my-org/shared-skills'],

        // Private repository with authentication
        [
            'repository' => 'my-org/private-skills',
            'token' => env('GITHUB_TOKEN'),
        ],

        // Custom branch and subdirectory
        [
            'repository' => 'my-org/monorepo',
            'path' => 'ai/skills',
            'branch' => 'develop',
            'token' => env('GITHUB_TOKEN'),
        ],
    ],
    'active_skills' => [
        'twig-component',
    ],
],
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

```php
// config/agent-skills.php
'skills' => [
    'enabled' => true,
    'agent' => \App\Agents\MyAgent::class,
    'directories' => [],
    'github_repositories' => [
        ['repository' => 'my-org/skills'],
    ],
    'active_skills' => [
        'my-skill',
    ],
],
```

## Skills as Tools

When ``active_skills`` are configured, the service provider automatically registers tool instances
for each active skill. These tools implement the ``Laravel\Ai\Contracts\Tool`` contract and can be
injected into your agents:

* ``GetSkillTool``: Load a specific skill by name with optional reference files
* ``GetSkillsTool``: Get all available skills
* ``ExecuteSkillScriptTool``: Execute a script from the skill's ``scripts/`` directory

Each active skill gets its own ``GetSkillTool`` and ``ExecuteSkillScriptTool`` instances, registered
in the container as ``agent_skills.tool.get_skill.{skill-name}`` and
``agent_skills.tool.execute_script.{skill-name}``.

To use skills as tools in your agent, implement the ``HasTools`` contract and return the tool
instances:

```php
use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillTool;
use AgentSkills\Bridge\Laravel\AI\Tool\GetSkillsTool;
use AgentSkills\Bridge\Laravel\AI\Tool\ExecuteSkillScriptTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class MyAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a helpful assistant with access to skills.';
    }

    public function tools(): iterable
    {
        return [
            app()->make(GetSkillsTool::class),
            app()->make('agent_skills.tool.get_skill.my-skill'),
            app()->make('agent_skills.tool.execute_script.my-skill'),
        ];
    }
}
```

## Skills as Context (Middleware)

The ``SkillPromptMiddleware`` injects skill instructions directly into the agent's prompt,
providing contextual knowledge before the LLM call. This is the equivalent of Symfony AI's
``SkillInputProcessor``.

To attach the middleware to your agent, implement the ``HasMiddleware`` contract:

```php
use AgentSkills\Bridge\Laravel\AI\Middleware\SkillPromptMiddleware;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

class MyAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a Symfony expert.';
    }

    public function middleware(): array
    {
        return [
            app()->make(SkillPromptMiddleware::class),
        ];
    }
}
```

The middleware builds a skill prompt with two optional sections:

1. **Skill Index** (when ``include_index`` is ``true``): A lightweight list of all discovered
   skills with their names and descriptions
2. **Active Skills** (when ``active_skills`` is non-empty): Full skill body content for each
   active skill

The assembled prompt is prepended to the agent's prompt via ``AgentPrompt::prepend()``.

This approach is ideal when:

* The agent needs consistent access to specific knowledge
* The skill content should influence all agent responses
* You want the agent to follow specific guidelines or patterns

## Skill Evaluation

The evaluation system measures how well an agent performs with and without a skill. Configure
it under the ``evaluation`` key:

```php
// config/agent-skills.php
'evaluation' => [
    'workspace' => storage_path('app/skill-evals'),
    'grading_model' => 'gpt-4o-mini',
    'grading_provider' => 'openai',
],
```

Configuration options:

* ``workspace`` (string): Directory to store evaluation results (timing, grading, benchmark JSON files)
* ``grading_model`` (string, optional): Model to use for LLM-based assertion grading (e.g. ``gpt-4o-mini``)
* ``grading_provider`` (string, optional): Provider name for the grading model (e.g. ``openai``)

> **Note:** Both ``grading_model`` and ``grading_provider`` must be configured to enable LLM grading.
> Without them, the evaluation still runs but assertions are not graded.

The Artisan commands ``ai:agent:validate-skills`` and ``ai:agent:eval-skill`` are automatically
registered when skills are enabled. See the `Commands`_ documentation for usage details.

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| ``AGENT_SKILLS_ENABLED`` | ``false`` | Enable or disable the skills integration |
| ``AGENT_SKILLS_AGENT`` | ``null`` | Agent class name for tool/eval registration |
| ``AGENT_SKILLS_EVAL_WORKSPACE`` | ``storage/app/skill-evals`` | Evaluation workspace directory |
| ``AGENT_SKILLS_GRADING_MODEL`` | ``null`` | LLM model for assertion grading |
| ``AGENT_SKILLS_GRADING_PROVIDER`` | ``null`` | Provider for the grading model |
| ``GITHUB_TOKEN`` | — | GitHub personal access token for private repos |
