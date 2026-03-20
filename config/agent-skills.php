<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Skills Configuration
    |--------------------------------------------------------------------------
    |
    | Configure skill loading, active skills, and agent integration.
    | Each agent can have its own set of skill directories, GitHub repositories,
    | active skills, and index settings.
    |
    */
    'skills' => [
        'enabled' => env('AGENT_SKILLS_ENABLED', false),

        'agents' => [
            // 'my_agent' => [
            //     'directories' => [
            //         resource_path('skills'),
            //     ],
            //     'github_repositories' => [
            //         // ['repository' => 'owner/repo', 'path' => '', 'branch' => 'main', 'token' => null],
            //     ],
            //     'active_skills' => [
            //         // 'my-skill',
            //     ],
            //     'include_index' => false,
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Evaluation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure evaluation workspaces and LLM grading for skill evaluations.
    |
    */
    'evaluation' => [
        'workspace' => env('AGENT_SKILLS_EVAL_WORKSPACE', 'storage/app/skill-evals'),

        'grading_model' => env('AGENT_SKILLS_GRADING_MODEL'),

        'grading_provider' => env('AGENT_SKILLS_GRADING_PROVIDER'),
    ],
];
