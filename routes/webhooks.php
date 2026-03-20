<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/{platform}', [WebhookController::class, 'handleWebhook']);
