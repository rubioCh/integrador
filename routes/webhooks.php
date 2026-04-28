<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/{client:slug}/{platform}', [WebhookController::class, 'handleWebhook']);
Route::post('/{platform}', [WebhookController::class, 'handleLegacyWebhook']);
