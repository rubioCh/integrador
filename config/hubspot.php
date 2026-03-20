<?php

return [
    'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    'base_url' => env('HUBSPOT_BASE_URL', 'https://api.hubapi.com'),
    'timeout_seconds' => (int) env('HUBSPOT_TIMEOUT_SECONDS', 30),
    'signed_quotes' => [
        'search_path' => env('HUBSPOT_SIGNED_QUOTES_PATH', '/crm/v3/objects/quotes/search'),
        'status_property' => env('HUBSPOT_SIGNED_QUOTES_STATUS_PROPERTY', 'hs_status'),
        'signed_status_value' => env('HUBSPOT_SIGNED_QUOTES_STATUS_VALUE', 'SIGNED'),
    ],
    'note_association_type_ids' => [
        'contacts' => (int) env('HUBSPOT_NOTE_ASSOC_CONTACTS', 202),
        'companies' => (int) env('HUBSPOT_NOTE_ASSOC_COMPANIES', 190),
        'deals' => (int) env('HUBSPOT_NOTE_ASSOC_DEALS', 214),
    ],
];
