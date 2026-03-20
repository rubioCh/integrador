<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PropertyController;

Route::get('/status', fn () => response()->json(['status' => 'ok']));

Route::middleware('auth.basic')->group(function () {
    Route::prefix('events')->group(function () {
        Route::get('/statistics', [EventController::class, 'statistics'])->middleware('permission:events.view');
        Route::get('/{event}/flow', [EventController::class, 'getEventFlow'])->middleware('permission:events.view');
        Route::get('/{event}/triggers', [EventController::class, 'getTriggers'])->middleware('permission:events.view');
        Route::post('/{event}/test', [EventController::class, 'testEvent'])->middleware('permission:events.manage');
        Route::post('/{event}/execute-flow', [EventController::class, 'executeEventFlow'])->middleware('permission:events.manage');
        Route::post('/{event}/execute-now', [EventController::class, 'executeNow'])->middleware('permission:events.manage');
        Route::put('/{event}/triggers', [EventController::class, 'updateTriggers'])->middleware('permission:events.manage');
    });

    Route::prefix('job-status')->group(function () {
        Route::get('/check', [JobStatusController::class, 'checkJobStatus'])->middleware('permission:records.view');
        Route::get('/related-records', [JobStatusController::class, 'checkRelatedRecords'])->middleware('permission:records.view');
    });

    Route::post('/platforms/{platform}/test-connection', [PlatformController::class, 'testConnection'])
        ->middleware('permission:platforms.manage');

    Route::apiResource('events', EventController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:events.manage');
    Route::apiResource('platforms', PlatformController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:platforms.manage');

    Route::apiResource('properties', PropertyController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:properties.manage');
    Route::apiResource('configs', ConfigController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:configs.manage');
    Route::apiResource('categories', CategoryController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:categories.manage');
    Route::apiResource('roles', RoleController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:roles.manage');
    Route::apiResource('users', UserController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:users.manage');
});
