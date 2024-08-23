<?php
//https://medium.com/@lpjfilho/filas-e-mensageria-com-rabbitmq-e-laravel-parte-1-fa3f92418f1b
//https://imasters.com.br/devsecops/clusterizando-rabbitmq-com-docker-compose

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::post('/registrando', [UserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// Para Rotas Autenticadas ->middleware(['auth:sanctum'])
