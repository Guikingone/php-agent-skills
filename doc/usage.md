# Usage

Agent Skills are reusable, portable knowledge packages that provide agents with specialized expertise in specific domains
or tasks. Following the `Agent Skills Specification`_, skills are self-contained directories with structured documentation,
optional reference materials, executable scripts, and assets.

Skills enable agents to:

 * Access domain-specific knowledge (frameworks, libraries, APIs)
 * Execute specialized tasks through scripts
 * Follow best practices and guidelines
 * Maintain consistent behavior across different contexts

## What are Skills?

A Skill is a directory containing a ``SKILL.md`` file with YAML frontmatter and Markdown instructions. The structure
follows the `Agent Skills Specification`_:

```bash
    my-skill/
    ├── SKILL.md           # Required: Skill definition with YAML frontmatter
    ├── scripts/           # Optional: Executable scripts (.sh, .py, .php, etc.)
    ├── references/        # Optional: Additional documentation
    └── assets/            # Optional: Images, files, etc.
```

The ``SKILL.md`` file contains:

**YAML Frontmatter** (required fields):

```yaml
---
name: my-skill
description: A comprehensive description of what the skill does and when to use it
license: MIT                           # Optional
allowed-tools: Read Write Bash         # Optional: space-delimited list
compatibility: claude >=3.5            # Optional: agent compatibility hints (max 500 chars)
metadata:                              # Optional: arbitrary metadata
  author: Your Name
  version: "1.0"
---
```

**Markdown Body**: Instructions, examples, and documentation for the agent to follow.

## Loading Skills

Skills are loaded using an implementation of :class:`AgentSkills\\SkillLoaderInterface`,
the default one is :class:`AgentSkills\\FilesystemSkillLoader` which scans directories for ``SKILL.md`` files:

```php
use AgentSkills\FilesystemSkillLoader;

$loader = new FilesystemSkillLoader([
    __DIR__.'/skills',
    __DIR__.'/vendor/skills',
]);

// Load a specific skill
$skill = $loader->loadSkill('my-skill');

// Load all skills
$skills = $loader->loadSkills();

// Discover skill metadata only (lightweight)
$metadata = $loader->discoverMetadata();
```

## Loading Skills from GitHub

The :class:`AgentSkills\\GithubSkillLoader` loads skills from GitHub repositories using
the `GitHub Contents API`_. This enables sharing and distributing skills across teams and projects
without local copies:

```php
use AgentSkills\GithubSkillLoader;
use Symfony\Component\HttpClient\HttpClient;

$loader = new GithubSkillLoader(
    [['repository' => 'owner/skills-repo']],
    HttpClient::create(),
);

// Same API as FilesystemSkillLoader
$skill = $loader->loadSkill('my-skill');
$skills = $loader->loadSkills();
$metadata = $loader->discoverMetadata();
```

Each repository entry supports the following options:

* ``repository`` (required): GitHub repository in ``owner/repo`` format or a full ``https://github.com/owner/repo`` URL
* ``path`` (optional): Subdirectory within the repository where skills are stored (default: root)
* ``branch`` (optional): Branch to load from (default: ``main``)
* ``token`` (optional): GitHub personal access token for private repositories

**Loading from a private repository with a custom path and branch**::

    $loader = new GithubSkillLoader(
        [[
            'repository' => 'my-org/private-skills',
            'path' => 'ai-skills',
            'branch' => 'develop',
            'token' => 'ghp_your_token_here',
        ]],
        HttpClient::create(),
    );

**Loading from multiple repositories**::

    $loader = new GithubSkillLoader(
        [
            ['repository' => 'my-org/common-skills'],
            ['repository' => 'my-org/team-skills', 'token' => 'ghp_token'],
        ],
        HttpClient::create(),
    );

The GitHub loader expects the same directory structure as the filesystem loader: each skill is a
directory containing a ``SKILL.md`` file, with optional ``scripts/``, ``references/``, and ``assets/``
subdirectories.

## Combining Loaders with ChainSkillLoader

The `AgentSkills\ChainSkillLoader` composes multiple loaders into a single
transparent loader. This is useful when skills come from both local directories and remote
repositories::

    use AgentSkills\ChainSkillLoader;
    use AgentSkills\FilesystemSkillLoader;
    use AgentSkills\GithubSkillLoader;
    use Symfony\Component\HttpClient\HttpClient;

    $localLoader = new FilesystemSkillLoader([__DIR__.'/skills']);
    $githubLoader = new GithubSkillLoader(
        [['repository' => 'my-org/shared-skills']],
        HttpClient::create(),
    );

    $loader = new ChainSkillLoader([$localLoader, $githubLoader]);

    // Uses the same SkillLoaderInterface API
    $skill = $loader->loadSkill('my-skill');

The chain loader follows a **first-match-wins** strategy:

* ``loadSkill()``: Returns the skill from the first loader that finds it
* ``loadSkills()`` and ``discoverMetadata()``: Aggregate results from all loaders; when a skill
  name exists in multiple loaders, the first loader's version takes precedence

This makes it easy to override remote skills with local versions by placing the local loader
first in the chain.

## Using Skills as Context

### Symfony AI Bridge

The `AgentSkills\Bridge\Symfony\AI\SkillInputProcessor` injects skill content directly into the system
prompt, providing the agent with contextual knowledge::

    use AgentSkills\Bridge\Symfony\AI\SkillInputProcessor;
    use AgentSkills\FilesystemSkillLoader;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Platform\Message\Message;
    use Symfony\AI\Platform\Message\MessageBag;

    // Initialize platform
    $loader = new FilesystemSkillLoader([__DIR__.'/skills']);

    // Inject the 'symfony-console' skill into the system context
    $skillProcessor = new SkillInputProcessor(
        $loader,
        ['symfony-console'],  // Skill names to include
        true                  // Include reference files
    );

    $agent = new Agent($platform, $model, [$skillProcessor]);

    $messages = new MessageBag(
        Message::forSystem('You are a Symfony expert.'),
        Message::ofUser('How do I create a console command?')
    );

    $result = $agent->call($messages);

This approach is ideal when:

* The agent needs consistent access to specific knowledge
* The skill content should influence all agent responses
* You want the agent to follow specific guidelines or patterns

### Laravel AI Bridge

The ``AgentSkills\Bridge\Laravel\AI\Middleware\SkillPromptMiddleware`` provides the same functionality
for Laravel AI agents. See the `Laravel AI documentation`_ for configuration and usage details.

# Using Skills as Tools

## Symfony AI Bridge

Skills can be exposed as callable tools using :class:`Symfony\\AI\\Agent\\Toolbox\\Tool\\SkillTool`, allowing the agent
to actively query skills when needed::

    use AgentSkills\FilesystemSkillLoader;
    use Symfony\AI\Agent\Agent;
    use Symfony\AI\Agent\Toolbox\AgentProcessor;
    use Symfony\AI\Agent\Toolbox\Tool\SkillTool;
    use Symfony\AI\Agent\Toolbox\Toolbox;
    use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;

    $loader = new FilesystemSkillLoader([__DIR__.'/skills']);
    $tool = new SkillTool($loader, 'twig-component');

    // Register the skill as a tool
    $toolFactory = new MemoryToolFactory();
    $toolFactory->addTool(
        $tool,
        'skill_twig_component',
        'Consult the twig-component skill for Symfony UX TwigComponent knowledge'
    );

    $processor = new AgentProcessor(new Toolbox([$tool], $toolFactory));
    $agent = new Agent($platform, $model, [$processor], [$processor]);

The :class:`Symfony\\AI\\Agent\\Toolbox\\Tool\\SkillTool` provides three built-in tools:

* ``get_skill``: Load a specific skill by name with optional reference files
* ``get_skills``: Get all available skills
* ``execute_skill_script``: Execute a script from the skill's ``scripts/`` directory

Loading Skills with References
..............................

Skills can reference additional documentation files::

    $result = $agent->call(new MessageBag(
        Message::forSystem('You are a helpful assistant.'),
        Message::ofUser('Show me advanced patterns for TwigComponent')
    ));

    // The agent can call: get_skill('twig-component', 'patterns.md')
    // This loads: skills/twig-component/references/patterns.md

This approach is ideal when:

* The agent should decide when to consult specific knowledge
* Skills contain large amounts of information
* You want to minimize token usage by loading skills on-demand

## Laravel AI Bridge

The Laravel AI bridge provides equivalent tool classes: ``GetSkillTool``, ``GetSkillsTool``, and
``ExecuteSkillScriptTool``. These are automatically registered in the container when ``active_skills``
are configured. See the `Laravel AI documentation`_ for configuration and usage details.

Executing Scripts from Skills
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Skills can include executable scripts in their ``scripts/`` directory. The :class:`Symfony\\AI\\Agent\\Toolbox\\Tool\\SkillTool`
automatically detects the interpreter based on file extension and executes scripts using Symfony's Process component::

    use Symfony\AI\Agent\Toolbox\Tool\SkillTool;

    // Skill structure:
    // my-skill/
    // ├── SKILL.md
    // └── scripts/
    //     ├── setup.sh
    //     ├── analyze.py
    //     └── process.php

    $loader = new FilesystemSkillLoader([__DIR__.'/skills']);
    $tool = new SkillTool($loader, 'my-skill');

    // Execute a script
    $result = $tool->executeScript('setup.sh');

    // Execute with arguments
    $result = $tool->executeScript('analyze.py', ['--input', 'data.csv']);

    // Execute with custom timeout (default: 60 seconds)
    $result = $tool->executeScript('process.php', ['arg1', 'arg2'], 120);

**Supported Script Types**:

* ``.php`` → Executed with PHP_BINARY
* ``.py`` → Executed with python3
* ``.sh`` → Executed with bash
* ``.js`` → Executed with node
* ``.rb`` → Executed with ruby
* Other extensions → Executed directly (requires execute permissions)

The script output (stdout and stderr) is captured and returned in a formatted markdown string. If the script fails,
both error output and standard output are included in the result.

.. warning::

    Scripts execute arbitrary code on your system. Only use skills from trusted sources and review script contents
    before execution. Always set appropriate timeouts and validate script inputs.

# Creating Your Own Skills

Create a new skill by following this structure:

**1. Create the directory and SKILL.md**::

    mkdir -p skills/my-skill
    cat > skills/my-skill/SKILL.md << 'EOF'
    ---
    name: my-skill
    description: Provides expertise in my-domain with best practices and examples
    license: MIT
    metadata:
        author: Your Name
        version: "1.0"
    ---

    # My Skill

    This skill provides guidance on my-domain.

    ## When to Use

    Use this skill when working with my-domain components or implementing my-feature.

    ## Best Practices

    - Always validate inputs
    - Use dependency injection
    - Follow PSR standards

    ## Examples

    ```php
    // Example code here
    ```
    EOF

**2. Add optional scripts**::

    mkdir skills/my-skill/scripts
    cat > skills/my-skill/scripts/validate.sh << 'EOF'
    #!/bin/bash
    echo "Validating configuration..."
    # Your validation logic
    EOF
    chmod +x skills/my-skill/scripts/validate.sh

**3. Add optional reference documentation**::

    mkdir skills/my-skill/references
    cat > skills/my-skill/references/api.md << 'EOF'
    # API Reference

    Detailed API documentation...
    EOF

**Best Practices**:

* Keep the main ``SKILL.md`` concise and actionable
* Use clear, kebab-case names (e.g., ``symfony-console``)
* Provide comprehensive descriptions (minimum 20 characters)
* Include trigger words in the description for better LLM matching
* Place detailed information in ``references/`` to avoid token bloat
* Document script parameters and expected behavior
* Include examples and common patterns

# Validating Skills

The :class:`AgentSkills\\Validation\\SkillValidator` validates skills against the specification::

    use AgentSkills\Validation\SkillValidator;

    $validator = new SkillValidator();
    $result = $validator->validate($skill);

    if (!$result->isValid()) {
        foreach ($result->getErrors() as $error) {
            echo $error;
        }
    }

Framework bridges also provide console commands::

    # Symfony
    php bin/console ai:agent:validate-skills

    # Laravel
    php artisan ai:agent:validate-skills

**Validation checks**:

* Required fields (name, description)
* Name format (must be kebab-case)
* Description length (warns if < 20 characters)
* Field types and formats
* Unknown frontmatter fields
* Body content presence
* Directory structure

**Output example**::

    Agent Skill Validation
    ======================

    Validating all skills...

    twig-component: ✓ valid
    symfony-console: ✓ valid ⚠ 1 warning(s)
      ⚠ Description is short (15 chars). Consider a more descriptive text

    Summary
    -------
     Metric         | Count
    ----------------+-------
     Total          | 2
     Valid          | 2
     Invalid        | 0
     With warnings  | 1

    [OK] All skills are valid! (1 warning(s) found)

**Exit Codes**:

* ``0`` (SUCCESS): All skills are valid
* ``1`` (FAILURE): At least one skill has validation errors

# Skill Discovery

The :class:`AgentSkills\\SkillLoaderInterface` provides methods for efficient skill discovery:

**Level 1 - Metadata Only** (Lightweight)::

    $loader = new FilesystemSkillLoader([__DIR__.'/skills']);

    // Get skill names only
    $names = $loader->getSkillsNames();
    // Returns: ['twig-component', 'symfony-console', ...]

    // Get metadata without loading full content
    $metadata = $loader->discoverMetadata();
    foreach ($metadata as $skillName => $meta) {
        echo $meta->getName();
        echo $meta->getDescription();
        echo $meta->getVersion();
    }

**Level 2 - Full Content**::

    // Load complete skill with body, scripts, references
    $skill = $loader->loadSkill('twig-component');
    echo $skill->getBody();
    echo $skill->loadReference('api.md');

Use Level 1 for listings, menus, or selection UIs. Use Level 2 when you need the actual skill content.

Evaluating Skills
^^^^^^^^^^^^^^^^^

The evaluation system measures how well an agent performs with and without a skill, using
LLM-based grading of assertions. This helps you validate that a skill actually improves
agent output quality before deploying it.

The evaluation flow is:

1. Write an ``evals.json`` test suite for your skill
2. Run the eval cases against an agent configured with the skill
3. Optionally run the same cases against a baseline agent (without the skill)
4. Grade outputs using an LLM grader that checks assertions
5. Compare benchmark results (pass rate, response time, token usage)

Writing an Evaluation Suite
...........................

Each skill can include an ``evals/`` directory containing an ``evals.json`` file::

    my-skill/
    ├── SKILL.md
    ├── evals/
    │   └── evals.json
    ├── references/
    └── scripts/

The ``evals.json`` file defines test cases for the skill:

.. code-block:: json

    {
        "skill_name": "my-skill",
        "evals": [
            {
                "id": 1,
                "prompt": "What is dependency injection?",
                "expected_output": "A design pattern that allows objects to receive dependencies from external sources rather than creating them internally.",
                "files": [],
                "assertions": [
                    "Mentions constructor injection",
                    "Explains benefits of loose coupling"
                ]
            },
            {
                "id": 2,
                "prompt": "Show me a service container example",
                "expected_output": "A container that manages service instantiation and dependency resolution.",
                "files": ["src/Container.php"],
                "assertions": [
                    "Includes a PHP code example",
                    "References the Container class"
                ]
            }
        ]
    }

**Required fields**:

* ``skill_name`` (string): Name of the skill being evaluated
* ``evals`` (array): Array of evaluation cases, each with:

    * ``id`` (integer): Unique identifier within the suite
    * ``prompt`` (string): User prompt to send to the agent
    * ``expected_output`` (string): Reference output for grading comparison

**Optional fields** (per eval case):

* ``files`` (array of strings): File paths to provide as additional context
* ``assertions`` (array of strings): Natural-language assertions that the LLM grader
  will evaluate against the agent output

Running Evaluations Programmatically
....................................

**Loading an evaluation suite**:

The :class:`AgentSkills\\Evaluation\\EvalSuiteLoader` loads and validates the
``evals/evals.json`` file from a skill directory::

    use AgentSkills\Evaluation\EvalSuiteLoader;

    $loader = new EvalSuiteLoader();
    $suite = $loader->load(__DIR__.'/skills/my-skill');

    echo $suite->getSkillName(); // "my-skill"

    foreach ($suite->getEvals() as $evalCase) {
        echo $evalCase->getPrompt();
        echo $evalCase->getExpectedOutput();
    }

    // Find a specific eval case by ID
    $case = $suite->getEvalById(1);

**Running eval cases against an agent**:

The :class:`AgentSkills\\Evaluation\\Runner\\EvalRunner` executes an eval case
against an agent executor and captures timing and token usage::

    use AgentSkills\Evaluation\Runner\EvalRunner;

    // $executor is an AgentExecutorInterface instance
    // For Symfony AI, use: new SymfonyAgentExecutor($agent)
    $runner = new EvalRunner($executor);

    foreach ($suite->getEvals() as $evalCase) {
        $result = $runner->run($evalCase);

        echo $result->getOutput();                    // Agent response text
        echo $result->getTiming()->getDurationMs();    // Execution time in ms
        echo $result->getTiming()->getTotalTokens();   // Total tokens used
    }

The :class:`AgentSkills\\Evaluation\\Runner\\AgentExecutorInterface` abstracts the agent call.
Each AI framework provides its own adapter:

* **Symfony AI**: :class:`AgentSkills\\Bridge\\Symfony\\AI\\Evaluation\\SymfonyAgentExecutor`

::

    use AgentSkills\Bridge\Symfony\AI\Evaluation\SymfonyAgentExecutor;

    // $agent is a Symfony\AI\Agent\AgentInterface instance
    $executor = new SymfonyAgentExecutor($agent);
    $runner = new EvalRunner($executor);

* **Laravel AI**: :class:`AgentSkills\\Bridge\\Laravel\\AI\\Evaluation\\LaravelAgentExecutor`

::

    use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelAgentExecutor;

    // $agent is a Laravel\Ai\Contracts\Agent instance
    $executor = new LaravelAgentExecutor($agent);
    $runner = new EvalRunner($executor);

**Grading with LLM**:

The :class:`AgentSkills\\Evaluation\\Grader\\LlmGrader` uses a language model
to evaluate each assertion against the agent output::

    use AgentSkills\Evaluation\Grader\LlmGrader;

    // $client is a LlmClientInterface instance
    // For Symfony AI, use: new SymfonyLlmClient($platform, 'gpt-4o-mini')
    $grader = new LlmGrader($client);

    $gradingResult = $grader->grade(
        $result->getOutput(),
        $evalCase->getAssertions(),
        $evalCase->getExpectedOutput(),
    );

    $summary = $gradingResult->getSummary();
    // ['passed' => 2, 'failed' => 0, 'total' => 2, 'pass_rate' => 1.0]

    foreach ($gradingResult->getAssertionResults() as $assertion) {
        echo $assertion->getText();      // "Mentions constructor injection"
        echo $assertion->isPassed();     // true
        echo $assertion->getEvidence();  // "The output explicitly discusses..."
    }

The :class:`AgentSkills\\Evaluation\\Grader\\LlmClientInterface` abstracts the LLM platform call.
Each AI framework provides its own adapter:

* **Symfony AI**: :class:`AgentSkills\\Bridge\\Symfony\\AI\\Evaluation\\SymfonyLlmClient`

::

    use AgentSkills\Bridge\Symfony\AI\Evaluation\SymfonyLlmClient;

    // $platform is a Symfony\AI\Platform\PlatformInterface instance
    $client = new SymfonyLlmClient($platform, 'gpt-4o-mini');
    $grader = new LlmGrader($client);

* **Laravel AI**: :class:`AgentSkills\\Bridge\\Laravel\\AI\\Evaluation\\LaravelLlmClient`

::

    use AgentSkills\Bridge\Laravel\AI\Evaluation\LaravelLlmClient;

    // $ai is a Laravel\Ai\AiManager instance
    $client = new LaravelLlmClient($ai, 'openai', 'gpt-4o-mini');
    $grader = new LlmGrader($client);

**Comparing with and without skill**:

The :class:`AgentSkills\\Evaluation\\Aggregator\\BenchmarkAggregator` computes
statistics (mean, standard deviation) from multiple eval runs and calculates the delta
between with-skill and without-skill results::

    use AgentSkills\Evaluation\Aggregator\BenchmarkAggregator;

    $aggregator = new BenchmarkAggregator();
    $benchmark = $aggregator->aggregate($withSkillResults, $withoutSkillResults);

    // Access statistics (mean + stddev)
    $benchmark->getWithSkillPassRate()->getMean();
    $benchmark->getWithoutSkillPassRate()->getMean();

    // Delta: positive = skill improved, negative = skill worsened
    $delta = $benchmark->getDelta();
    // ['pass_rate' => 0.25, 'time_ms' => -150.0, 'tokens' => -200.0]

**Persisting results**:

The :class:`AgentSkills\\Evaluation\\Workspace\\WorkspaceManager` saves all
evaluation artifacts as JSON files in a structured directory::

    use AgentSkills\Evaluation\Workspace\WorkspaceManager;

    $workspace = new WorkspaceManager('/path/to/workspace');
    $workspace->initializeIteration(1);

    $evalDir = $workspace->getEvalDirectory(1, '1', 'with_skill');
    $workspace->saveOutput($evalDir, $result->getOutput());
    $workspace->saveTimingResult($evalDir, $result->getTiming());
    $workspace->saveGradingResult($evalDir, $gradingResult);
    $workspace->saveBenchmarkResult(1, $benchmark);

### Workspace Output Structure

After an evaluation run, the workspace directory contains:

```bash
var/skill-evals/
└── iteration-1/
    ├── eval-1/
    │   ├── with_skill/
    │   │   ├── outputs/
    │   │   ├── timing.json
    │   │   └── grading.json
    │   └── without_skill/
    │       ├── outputs/
    │       ├── timing.json
    │       └── grading.json
    ├── eval-2/
    │   └── ...
    └── benchmark.json
```

Each ``timing.json`` contains token count and duration. Each ``grading.json`` contains
per-assertion pass/fail results with evidence. The ``benchmark.json`` contains aggregated
statistics comparing with-skill and without-skill runs.

# Code Examples

* `Skills as Context`_: Inject skills into system prompt
* `Skills as Tools`_: Expose skills as callable tools
* `GitHub Skills as Context`_: Load skills from GitHub and inject into system prompt
* `GitHub Skills as Tools`_: Load skills from GitHub and expose as callable tools
* `Chain Loader`_: Combine local and GitHub loaders transparently
* `Script Execution`_: Execute scripts from skills
* `Skill Validation`_: Validate skill structure
