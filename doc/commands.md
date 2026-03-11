# `ai:agent:eval-skill`

The ``ai:agent:eval-skill`` command evaluates an Agent Skill using its ``evals/evals.json`` test suite.
It runs each eval case against a configured agent, optionally grades assertions using an LLM, and
compares results against a baseline agent.

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
