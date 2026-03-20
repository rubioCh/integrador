<?php

return [
    'account' => env('NETSUITE_ACCOUNT'),
    'consumer_key' => env('NETSUITE_CONSUMER_KEY'),
    'consumer_secret' => env('NETSUITE_CONSUMER_SECRET'),
    'token_id' => env('NETSUITE_TOKEN_ID'),
    'token_secret' => env('NETSUITE_TOKEN_SECRET'),
    'private_key' => env('NETSUITE_PRIVATE_KEY'),
    'base_url' => env('NETSUITE_BASE_URL'),
    'timeout_seconds' => (int) env('NETSUITE_TIMEOUT_SECONDS', 30),
];
