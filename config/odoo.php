<?php

return [
    'url' => env('ODOO_URL'),
    'database' => env('ODOO_DATABASE'),
    'username' => env('ODOO_USERNAME'),
    'password' => env('ODOO_PASSWORD'),
    'timeout_seconds' => (int) env('ODOO_TIMEOUT_SECONDS', 30),
];
