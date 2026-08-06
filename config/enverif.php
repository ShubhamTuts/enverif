<?php
return [
    'tagline' => 'The open-source agent operating system for revenue.',
    'default_provider' => env('ENVERIF_DEFAULT_PROVIDER', 'openai'),
    'run' => ['max_steps'=>(int)env('ENVERIF_MAX_STEPS',40),'max_runtime_seconds'=>(int)env('ENVERIF_MAX_RUNTIME_SECONDS',900),'max_cost_usd'=>(float)env('ENVERIF_MAX_COST_USD',10),'max_delegation_depth'=>(int)env('ENVERIF_MAX_DELEGATION_DEPTH',3)],
    'runtime' => [
        'mode' => env('ENVERIF_RUNTIME_MODE', 'shared'),
        'tick_budget' => (int) env('ENVERIF_TICK_BUDGET', 45),
        'web_kick_budget' => (int) env('ENVERIF_WEB_KICK_BUDGET', 20),
        'web_cron' => [
            'enabled' => filter_var(env('ENVERIF_WEB_CRON_ENABLED', false), FILTER_VALIDATE_BOOL),
            'secret' => env('ENVERIF_WEB_CRON_SECRET', ''),
        ],
    ],
    'trusted_skill_hosts' => ['github.com','gitlab.com','codeberg.org'],
    'locales' => ['en'=>'English','fr'=>'Français','nl'=>'Nederlands'],
];
