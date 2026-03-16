# Commands

`ai:agent:eval-skill`
---------------------

The ``ai:agent:eval-skill`` command evaluates an Agent Skill using its ``evals/evals.json`` test suite.
It runs each eval case against a configured agent, optionally grades assertions using an LLM, and
compares results against a baseline agent.

**Symfony**:

```bash
$ php bin/console ai:agent:eval-skill <skill-directory> --agent=<agent>

# Evaluate a skill using the "my_agent" agent
$ php bin/console ai:agent:eval-skill skills/my-skill --agent=my_agent

# Compare with a baseline agent (without the skill)
$ php bin/console ai:agent:eval-skill skills/my-skill --agent=my_agent --baseline-agent=baseline_agent

# Run a specific iteration (useful for repeated benchmarks)
$ php bin/console ai:agent:eval-skill skills/my-skill --agent=my_agent --iteration=3

# Skip LLM grading (only measure timing and token usage)
$ php bin/console ai:agent:eval-skill skills/my-skill --agent=my_agent --skip-grading
```

**Laravel**:

```bash
$ php artisan ai:agent:eval-skill <skill-directory> --agent=<agent>

# Evaluate a skill using the "App\Agents\MyAgent" agent
$ php artisan ai:agent:eval-skill skills/my-skill --agent="App\Agents\MyAgent"

# Compare with a baseline agent (without the skill)
$ php artisan ai:agent:eval-skill skills/my-skill --agent="App\Agents\MyAgent" --baseline-agent="App\Agents\BaselineAgent"

# Run a specific iteration (useful for repeated benchmarks)
$ php artisan ai:agent:eval-skill skills/my-skill --agent="App\Agents\MyAgent" --iteration=3

# Skip LLM grading (only measure timing and token usage)
$ php artisan ai:agent:eval-skill skills/my-skill --agent="App\Agents\MyAgent" --skip-grading
```

> **Note:** In Laravel, the ``--agent`` option takes a fully-qualified class name (FQCN) instead of a
> service name. The agent class is resolved from the container. The ``agent`` config key in
> ``config/agent-skills.php`` must be set for this command to be registered.

**Arguments**:

* ``skill-directory`` (required): Path to the skill directory containing ``evals/evals.json``
* ``workspace`` (optional): Path to the workspace directory for storing results (overrides config)

**Options**:

* ``--agent`` (required): Agent service name to evaluate (must be a configured agent)
* ``--baseline-agent`` (optional): Agent service name for baseline comparison (without skill)
* ``--iteration``, ``-i`` (default: ``1``): Iteration number for organizing results
* ``--skip-grading``: Skip LLM grading, only capture timing and output

When a baseline agent is provided, the command displays a benchmark comparison table:

.. code-block:: text

    Benchmark Results
    =================

     Metric      | With Skill | Without Skill | Delta
    -------------+------------+---------------+--------
     Pass Rate   | 0.85       | 0.60          | +0.25
     Time (ms)   | 1200       | 1050          | +150
     Tokens      | 850        | 620           | +230

All results are persisted in the workspace directory as JSON files for later analysis.

`ai:agent:validate-skills`
--------------------------

The ``ai:agent:validate-skills`` command validates Agent Skills against the specification.
It checks each skill's ``SKILL.md`` structure, required metadata, and optional references.

**Symfony**:

```bash
# Validate all discovered skills
$ php bin/console ai:agent:validate-skills

# Validate a specific skill by name
$ php bin/console ai:agent:validate-skills --skill=twig-component
```

**Laravel**:

```bash
# Validate all discovered skills
$ php artisan ai:agent:validate-skills

# Validate a specific skill by name
$ php artisan ai:agent:validate-skills --skill=twig-component
```

**Options**:

* ``--skill`` (optional): Name of a specific skill to validate. When omitted, all discovered skills are validated.

**Output**:

The command displays a table with the validation status of each skill:

```text
 Skill           | Status  | Errors | Warnings
-----------------+---------+--------+----------
 twig-component  | valid   |      0 |        1
 code-review     | invalid |      2 |        0
```

When errors or warnings are found, they are listed with details:

```text
 * [code-review] error: Missing required metadata field "description"
 * [code-review] error: SKILL.md body is empty
 * [twig-component] warning: No references directory found
```

**Exit codes**:

* ``0``: All skills are valid (or no skills found)
* ``1``: One or more skills have validation errors
