<?php

namespace App\Console\Commands;

use App\Mail\AnswerNotification;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailRegistrationOpp;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diligence:consumer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cosumidor das filas do Rabbitmq para as diligências';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = env('RABBITMQ_QUEUE_PC');
        $connection = new AMQPStreamConnection(
            env('RABBITMQ_DEFAULT_HOST'),
            env('RABBITMQ_DEFAULT_PORT'),
            env('RABBITMQ_DEFAULT_USER'),
            env('RABBITMQ_DEFAULT_PASS'),
            '/'
        );
        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        $callback = function ($msg) {
            $data = json_decode($msg->body);
            if( $msg->getRoutingKey() == env('RABBITMQ_QUEUE_PC_ROUTE_KEY_PROP') )
            {
                Mail::to($data->email)->send(new EmailRegistrationOpp(
                    $data->name,
                    $data->number,
                    $data->days
                ));
            }
            if($msg->getRoutingKey() == env('RABBITMQ_QUEUE_PC_ROUTE_KEY_ADM'))
            {
                Mail::to($data->comission)->cc($data->owner)->send(new AnswerNotification(
                    $data->registration,
                ));
            };
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
        };
        $channel->basic_qos(null, 1, null);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);
        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();

        return Command::SUCCESS;

    }
}
