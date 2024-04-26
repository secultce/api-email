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
    protected $signature = 'rabbitmq:consumer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $exchange = 'router';
        $queue = 'msgs';
//        $consumerTag = 'consumer';
        $connection = new AMQPStreamConnection('rabbitmq', '5672', 'mqadmin', 'Admin123XX_', '/');
        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);
        echo " [*] Waiting for messages. To exit press CTRL+C\n";

        $callback = function ($msg) {

            echo ' [x] Received ', $msg->body, "\n";
            $data = json_decode($msg->body);
            if($msg->getRoutingKey() == 'proponente')
            {
                Mail::to($data->email)->send(new EmailRegistrationOpp(
                    $data->name,
                    $data->number,
                    $data->days
                ));
            }
            if($msg->getRoutingKey() == 'resposta')
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
