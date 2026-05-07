<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebhookController;

Route::post('/{client:slug}/{platform}', [WebhookController::class, 'handleWebhook']);
Route::post('/{client:slug}/treble/status', [WebhookController::class, 'handleTrebleStatusWebhook']);
