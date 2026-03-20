<?php

return [
    'configs' => [
        [
            'name' => 'webhook',
            'signing_secret' => env('WEBHOOK_CLIENT_SECRET'),
            'signature_header_name' => 'Signature',
            'signature_validator' => \App\WebhookClient\WebhookCustomSignatureValidator::class,
            'webhook_profile' => \App\WebhookClient\WebhookCustomProfile::class,
            'webhook_response' => \App\WebhookClient\WebhookCustomResponse::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \App\Jobs\WebhookCustomProcessJob::class,
            'store_headers' => [],
        ],
    ],
    'delete_after_days' => 30,
];
