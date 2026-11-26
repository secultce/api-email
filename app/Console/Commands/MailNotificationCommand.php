<?php

namespace App\Console\Commands;

use App\Services\InvalidRegistrationMessageException;
use App\Services\MailNotificationProcessor;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MailNotificationCommand extends Command
{
    protected $signature = 'rabbitmq:mail-notification';

    protected $description = 'Consome os e-mails de inscrição iniciada/enviada do Mapas Culturais';

    public function __construct(
        protected RabbitMQService $queueService,
        protected MailNotificationProcessor $processor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('rabbitmq.enabled', true)) {
            Log::info('Consumidor de notificações de inscrição  desabilitado pela configuração.');

            return self::SUCCESS;
        }

        $queue = config('rabbitmq.queues.queue_mail_notification');

        try {
            $this->queueService->consume(
                $queue,
                config('rabbitmq.exchange_default'),
                config('rabbitmq.routing.module_mail_notification'),
                function (AMQPMessage $msg): void {
                    $this->processMessage($msg);
                }
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::critical('O consumidor RabbitMQ foi encerrado por uma falha.', [
                'exception' => $e->getMessage(),
                'queue' => $queue,
            ]);

            report($e);

            return self::FAILURE;
        }
    }

    protected function processMessage(AMQPMessage $msg): void
    {
        $body = $msg->getBody();
        $context = ['body_sha256' => hash('sha256', $body)];

        try {
            try {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidRegistrationMessageException('O corpo da mensagem não contém um JSON válido.', 0, $e);
            }

            if (!is_array($data)) {
                throw new InvalidRegistrationMessageException('A mensagem deve conter um objeto JSON.');
            }

            $context += [
                'message_id' => $data['message_id'] ?? null,
                'registration_id' => $data['registration_id'] ?? null,
            ];

            $this->processor->process($data);
        } catch (InvalidRegistrationMessageException $e) {
            Log::error('Mensagem de notificação de inscrição inválida.', $context + ['exception' => $e->getMessage()]);

            $msg->nack(false, false); // DLQ

            return;
        } catch (Throwable $e) {
            Log::error('Falha ao enviar notificação de inscrição.', $context + ['exception' => $e->getMessage()]);

            report($e);

            $msg->nack(false, false); // DLQ; não reenviar SMTP incerto

            return;
        }

        $msg->ack();
    }
}
