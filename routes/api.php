<?php
//https://medium.com/@lpjfilho/filas-e-mensageria-com-rabbitmq-e-laravel-parte-1-fa3f92418f1b
//https://imasters.com.br/devsecops/clusterizando-rabbitmq-com-docker-compose

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

Route::get('send', function() {
    $exchange = 'email';
    $queue = 'envioEmailNfe';

    $connection = new AMQPStreamConnection('rabbitmq', '5672', 'mqadmin', 'Admin123XX_', '/');
    $channel = $connection->channel();
    $channel->exchange_declare($exchange, false, true, false, false);

    $rabbitMsg = new AMQPMessage('Enviou Will');
    $channel->basic_publish($rabbitMsg, $exchange, $queue);
    $channel->close();
    $connection->close();
});

Route::post('/registrando', [UserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// Para Rotas Autenticadas ->middleware(['auth:sanctum'])
Route::post('/send/draft',[UserController::class, 'draft'] );
