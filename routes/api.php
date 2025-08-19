<?php

// https://medium.com/@lpjfilho/filas-e-mensageria-com-rabbitmq-e-laravel-parte-1-fa3f92418f1b
// https://imasters.com.br/devsecops/clusterizando-rabbitmq-com-docker-compose

use App\Http\Controllers\RankingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/registrando', [UserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// Para Rotas Autenticadas ->middleware(['auth:sanctum'])
Route::post('/processar-candidatos', [RankingController::class, 'processar']);
