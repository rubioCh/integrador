<?php

return [
    'queues' => [
        'webhooks' => 'webhooks',
        'events' => 'events',
        'creation' => 'creation',
        'update' => 'update',
        'sync' => 'sync',
        'signed_quotes' => 'signed-quotes',
        'validation' => 'validation',
        'processing' => 'processing',
    ],
    'priority' => [
        'webhooks',
        'creation',
        'update',
        'signed-quotes',
        'validation',
        'sync',
        'processing',
        'events',
    ],
    'timeouts' => [
        'creation' => 300,
        'update' => 180,
        'sync' => 900,
        'signed-quotes' => 900,
        'list-prices' => 300,
        'processing' => 300,
    ],
    'tries' => [
        'creation' => 3,
        'update' => 3,
        'sync' => 2,
        'signed-quotes' => 1,
        'list-prices' => 2,
    ],
    'backoff' => [
        'creation' => 60,
        'update' => 30,
        'sync' => 300,
        'signed-quotes' => 300,
        'list-prices' => 60,
    ],
];
