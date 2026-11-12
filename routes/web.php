<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat');

// Each message can trigger several model calls, so rate-limit it per IP.
Route::post('/chat', [ChatController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('chat.store');
