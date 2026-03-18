# Neuron AI Integration

The Neuron AI integration provides support for loading and using `Agent Skills`_ within
applications powered by ``neuron-core/neuron-ai``.

## Installation

Install the package alongside ``neuron-core/neuron-ai``:

```bash
composer require guikingone/agent-skills neuron-core/neuron-ai
```

Since Neuron AI is framework-agnostic, there is no service provider or bundle to configure.
You wire the bridge classes directly in your agent code.

## Skills as Context

The ``SkillSystemPromptBuilder`` builds skill instruction text that you include in your
agent's ``instructions()`` method. This mirrors the Symfony AI ``SkillInputProcessor`` and
Laravel AI ``SkillPromptMiddleware`` but without any framework-specific pipeline.

```php
use AgentSkills\Bridge\NeuronAI\SkillSystemPromptBuilder;
use AgentSkills\FilesystemSkillLoader;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;

class MyAgent extends Agent
{
    private SkillSystemPromptBuilder $skillPromptBuilder;

    public function __construct()
    {
        $loader = new FilesystemSkillLoader([__DIR__ . '/skills']);

        $this->skillPromptBuilder = new SkillSystemPromptBuilder(
            $loader,
            activeSkills: ['code-review'],
            includeIndex: true,
        );
    }

    protected function resolveProvider(): AIProviderInterface
    {
        return new OpenAI('gpt-4o');
    }

    protected function instructions(): string
    {
        $base = 'You are a helpful assistant.';

        return $base . ($this->skillPromptBuilder->build() ?? '');
    }
}
```

The builder produces two optional sections:

1. **Skill Index** (when ``includeIndex`` is ``true``): A lightweight list of all discovered
   skills with their names and descriptions
2. **Active Skills** (when ``activeSkills`` is non-empty): Full skill body content for each
   active skill

The ``build()`` method returns ``null`` when no skills are available, allowing easy
null-coalescing in your instructions.

## Skills as Tools

The ``SkillToolFactory`` creates Neuron AI ``Tool`` instances that wrap skill operations.
Register them with your agent via ``addTool()``:

```php
use AgentSkills\Bridge\NeuronAI\Tool\SkillToolFactory;
use AgentSkills\FilesystemSkillLoader;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class ToolAgent extends Agent
{
    public function __construct()
    {
        $loader = new FilesystemSkillLoader([__DIR__ . '/skills']);
        $factory = new SkillToolFactory($loader);

        // Register a tool to list all skills
        $this->addTool($factory->createGetSkillsTool());

        // Register per-skill tools
        $this->addTool($factory->createGetSkillTool('code-review'));
        $this->addTool($factory->createExecuteSkillScriptTool('code-review'));
    }

    protected function resolveProvider(): AIProviderInterface
    {
        return new Anthropic('claude-sonnet-4-20250514');
    }

    protected function instructions(): string
    {
        return 'You are an assistant with access to skills.';
    }
}
```

Three tool types are available:

* ``createGetSkillTool(string $skillName)``: Load a specific skill by name with optional
  reference files. Registered as ``get_skill_{name}``
* ``createGetSkillsTool()``: List all available skills. Registered as ``get_skills``
* ``createExecuteSkillScriptTool(string $skillName)``: Execute a script from the skill's
  ``scripts/`` directory. Registered as ``execute_skill_script_{name}``

## Using the SkillAwareAgent Trait

The ``SkillAwareAgent`` trait combines prompt building and tool registration into a single
``configureSkills()`` call, reducing boilerplate:

```php
use AgentSkills\Bridge\NeuronAI\SkillAwareAgent;
use AgentSkills\FilesystemSkillLoader;
use NeuronAI\Agent\Agent;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;

class SmartAgent extends Agent
{
    use SkillAwareAgent;

    public function __construct()
    {
        $loader = new FilesystemSkillLoader([__DIR__ . '/skills']);

        $this->configureSkills(
            $loader,
            activeSkills: ['code-review', 'testing'],
            includeIndex: true,
            registerTools: true,
        );
    }

    protected function resolveProvider(): AIProviderInterface
    {
        return new Anthropic('claude-sonnet-4-20250514');
    }

    protected function instructions(): string
    {
        return 'You are a Symfony expert.' . $this->skillInstructions();
    }
}
```

The ``configureSkills()`` method:

* Creates a ``SkillSystemPromptBuilder`` for prompt injection
* When ``registerTools`` is ``true`` (default), registers ``get_skills`` plus per-skill
  ``get_skill_{name}`` and ``execute_skill_script_{name}`` tools

The ``skillInstructions()`` method returns the assembled prompt text, or an empty string
if skills are not configured.

## GitHub Skills

Skills can be loaded from GitHub repositories using the ``GithubSkillLoader``. When combined
with local directories, use the ``ChainSkillLoader``:

```php
use AgentSkills\ChainSkillLoader;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\GithubSkillLoader;
use Symfony\Component\HttpClient\HttpClient;

$filesystemLoader = new FilesystemSkillLoader([__DIR__ . '/skills']);
$githubLoader = new GithubSkillLoader(
    [
        ['repository' => 'my-org/shared-skills'],
        ['repository' => 'my-org/private-skills', 'token' => $_ENV['GITHUB_TOKEN']],
    ],
    HttpClient::create(),
);

$loader = new ChainSkillLoader([$filesystemLoader, $githubLoader]);
```

Local skills take precedence over GitHub skills with the same name.

## Skill Evaluation

The evaluation system measures how well an agent performs with and without a skill.
Since Neuron AI has no CLI framework, evaluations are run programmatically.

### Agent Executor

The ``NeuronAgentExecutor`` adapts Neuron AI's ``AgentInterface`` to the framework-agnostic
``AgentExecutorInterface`` used by the evaluation runner:

```php
use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronAgentExecutor;
use AgentSkills\Evaluation\Runner\EvalRunner;
use Symfony\Component\Clock\NativeClock;

$executor = new NeuronAgentExecutor($agent);
$runner = new EvalRunner($executor, new NativeClock());

$result = $runner->run($evalCase);
```

### LLM Client for Grading

The ``NeuronLlmClient`` adapts Neuron AI's ``AIProviderInterface`` to the ``LlmClientInterface``
used by the LLM grader:

```php
use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronLlmClient;
use AgentSkills\Evaluation\Grader\LlmGrader;
use NeuronAI\Providers\OpenAI\OpenAI;

$provider = new OpenAI('gpt-4o-mini');
$llmClient = new NeuronLlmClient($provider);
$grader = new LlmGrader($llmClient);

$grading = $grader->grade($output, $assertions, $expectedOutput);
```

### Complete Evaluation Example

```php
use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronAgentExecutor;
use AgentSkills\Bridge\NeuronAI\Evaluation\NeuronLlmClient;
use AgentSkills\Evaluation\Aggregator\BenchmarkAggregator;
use AgentSkills\Evaluation\EvalSuiteLoader;
use AgentSkills\Evaluation\Grader\LlmGrader;
use AgentSkills\Evaluation\Runner\EvalRunner;
use AgentSkills\Evaluation\Workspace\WorkspaceManager;
use NeuronAI\Providers\OpenAI\OpenAI;
use Symfony\Component\Clock\NativeClock;

// Load the eval suite
$suiteLoader = new EvalSuiteLoader();
$suite = $suiteLoader->load('/path/to/skill');

// Set up evaluation components
$workspace = new WorkspaceManager('/path/to/results');
$clock = new NativeClock();
$graderProvider = new OpenAI('gpt-4o-mini');
$grader = new LlmGrader(new NeuronLlmClient($graderProvider));

// Run with-skill evals
$withSkillRunner = new EvalRunner(new NeuronAgentExecutor($agentWithSkill), $clock);
$withSkillResults = [];

foreach ($suite->getEvals() as $evalCase) {
    $result = $withSkillRunner->run($evalCase);
    $grading = $grader->grade($result->getOutput(), $evalCase->getAssertions(), $evalCase->getExpectedOutput());
    $result = $result->withGrading($grading);
    $withSkillResults[] = $result;
}

// Run without-skill evals (baseline)
$baselineRunner = new EvalRunner(new NeuronAgentExecutor($agentWithoutSkill), $clock);
$baselineResults = [];

foreach ($suite->getEvals() as $evalCase) {
    $result = $baselineRunner->run($evalCase);
    $grading = $grader->grade($result->getOutput(), $evalCase->getAssertions(), $evalCase->getExpectedOutput());
    $result = $result->withGrading($grading);
    $baselineResults[] = $result;
}

// Compare results
$aggregator = new BenchmarkAggregator();
$benchmark = $aggregator->aggregate($withSkillResults, $baselineResults);
$delta = $benchmark->getDelta();

echo sprintf("Pass rate delta: %+.2f\n", $delta['pass_rate']);
echo sprintf("Time delta: %+.1f seconds\n", $delta['time_seconds']);
echo sprintf("Token delta: %+.0f\n", $delta['tokens']);
```
