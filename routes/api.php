<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/registrando', [UserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

Route::post('/send/draft',[UserController::class, 'draft'] )->middleware(['auth:sanctum']);
