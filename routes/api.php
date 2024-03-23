<?php
//https://medium.com/@lpjfilho/filas-e-mensageria-com-rabbitmq-e-laravel-parte-1-fa3f92418f1b
//https://imasters.com.br/devsecops/clusterizando-rabbitmq-com-docker-compose

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailRegistrationOpp;

function process_message($message)
{
    echo "\n--------\n";
    echo $message->body;
    echo "\n--------\n";

//    $message->ack();

    // Send a message with the string "quit" to cancel the consumer.
    if ($message->body === 'quit') {
        $message->getChannel()->basic_cancel($message->getConsumerTag());
    }
}

Route::get('send', function() {
    $email = Mail::to('franciscoanto@gmail.com')->send(new EmailRegistrationOpp());
    dump($email);
//    $channel->close();
//    $connection->close();
});



Route::post('/registrando', [UserController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// Para Rotas Autenticadas ->middleware(['auth:sanctum'])
Route::post('/send/draft',[UserController::class, 'draft'] );
