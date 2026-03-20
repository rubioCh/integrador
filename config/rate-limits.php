<?php

return [
    'platforms' => [
        'hubspot' => env('HUBSPOT_RATE_LIMIT_RPS'),
        'odoo' => env('ODOO_RATE_LIMIT_RPS'),
        'netsuite' => env('NETSUITE_RATE_LIMIT_RPS'),
    ],
    'backoff' => [
        'base_ms' => (int) env('RATE_LIMIT_BACKOFF_BASE_MS', 250),
        'max_ms' => (int) env('RATE_LIMIT_BACKOFF_MAX_MS', 5000),
        'jitter' => filter_var(env('RATE_LIMIT_JITTER', true), FILTER_VALIDATE_BOOL),
    ],
];
