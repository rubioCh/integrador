<?php

use Illuminate\Support\Facades\Route;

Route::get('/status', fn () => response()->json(['status' => 'ok']));
