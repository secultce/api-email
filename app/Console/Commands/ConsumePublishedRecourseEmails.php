<?php

namespace App\Console\Commands;

use App\Mail\PublishedRecourse;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumePublishedRecourseEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume-published-recourse-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consome os e-mails de recursos publicados da fila do RabbitMQ e os envia';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        $queue = config('rabbitmq.queues.queue_published_recourses');
        $connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.pass'),
        );
        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, true, false);

        $this->info('🎯 Aguardando e-mails para envio...');

        $channel->basic_consume(queue: $queue, callback: $this->processMessage(...));

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    private function sendEmail($data): bool
    {
        try {
            Mail::to($data['email'])->send(new PublishedRecourse($data));

            return true;
        } catch (Exception $e) {
            logger($e->getMessage());

            return false;
        }
    }

    protected function processMessage(AMQPMessage $msg): void
    {
        $data = json_decode($msg->body, true);

        if ($this->sendEmail($data)) {
            $msg->ack(); // Confirma o processamento
            $this->info("📧 E-mail enviado para: {$data['email']}");

            return;
        }

        $this->error("❌ Falha ao enviar e-mail para: {$data['email']}");
    }
}
