<?php

namespace App\Console\Commands;

use App\Mail\AnswerNotification;
use App\Mail\EmailRegistrationOpp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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
    protected $description = 'Consumidor das filas do Rabbitmq para as diligências';

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(): int
    {
        $queue = config('rabbitmq.queues.queue_accountability');

        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.pass'),
            '/',
        );

        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        $channel->basic_qos(null, 1, null);
        $channel->basic_consume(queue: $queue, callback: $this->processMessage(...));

        while ($channel->is_consuming()) {
            $this->info('🎯 Aguardando e-mails para envio...');
            $channel->wait();
        }

        $channel->close();

        return Command::SUCCESS;
    }

    protected function processMessage(AMQPMessage $msg): void
    {
        $data = json_decode($msg->body);

        if ($msg->getRoutingKey() == config('rabbitmq.routing.module_accountability_proponent')) {
            Mail::to($data->email)->send(new EmailRegistrationOpp(
                $data->name,
                $data->number,
                $data->days
            ));
        }

        if ($msg->getRoutingKey() == config('rabbitmq.routing.module_accountability_adm')) {
            Mail::to($data->comission)->cc($data->owner)->send(new AnswerNotification(
                $data->registration
            ));
        }

        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }
}
