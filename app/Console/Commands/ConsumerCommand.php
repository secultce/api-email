<?php

namespace App\Console\Commands;

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
//            echo ' [x] Received ', $msg->body, "\n";
            $data = json_decode($msg->body);

//            echo ' [x] Received ', $data[0]->email, "\n";;
            foreach ($data as $userData)
            {
                echo ' [x] Received ', $userData->email, "\n";
               try{
                   Mail::to($userData->email)->send(new EmailRegistrationOpp());
               }catch (\Exception $e)
               {
                   var_dump($e->getMessage());
               }
            }

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
