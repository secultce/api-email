<?php

namespace App\Console\Commands;

use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MailNotificationCommand extends Command
{
    protected $signature = 'rabbitmq:mail-notification';

    protected $description = 'Consome os e-mails de inscrição iniciada/enviada do Mapas Culturais';

    public function __construct(protected RabbitMQService $queueService)
    {
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
        $data = json_decode($msg->getBody(), true);

        $validator = Validator::make(is_array($data) ? $data : [], [
            'message_id' => ['required', 'string'],
            'event' => ['sometimes', 'string', 'in:registration.start,registration.send'],
            'registration_id' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'email'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            Log::error('Mensagem de notificação de inscrição inválida.', [
                'errors' => $validator->errors()->toArray(),
                'body_sha256' => hash('sha256', $msg->getBody()),
            ]);

            $msg->nack(false, false); // DLQ

            return;
        }

        $context = [
            'message_id' => $data['message_id'],
            'event' => $data['event'] ?? null,
            'registration_id' => $data['registration_id'],
        ];

        // O job do Mapas pode ser reprocessado e publicar a mesma mensagem de novo
        $alreadyProcessed = EmailDispatch::query()
            ->where('mailable_type', MailNotification::class)
            ->where('meta->message_id', $data['message_id'])
            ->whereIn('status', ['sending', 'sent'])
            ->exists();

        if ($alreadyProcessed) {
            Log::warning('Mensagem duplicada ou já processada.', $context);
            $msg->ack();

            return;
        }

        $dispatch = null;

        try {
            $dispatch = EmailDispatch::create([
                'to' => $data['to'],
                'subject' => $data['subject'],
                'content' => $data['body'],
                'mailable_type' => MailNotification::class,
                'meta' => $context,
                'status' => 'sending',
                'attempted_at' => now(),
            ]);

            Mail::to($data['to'])->send(new MailNotification($data['subject'], $data['body']));

            $dispatch->update([
                'status' => 'sent',
                'dispatched_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao enviar notificação de inscrição.', $context + [
                'exception' => $e->getMessage(),
            ]);

            report($e);

            $dispatch?->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            $msg->nack(false, false); // DLQ; não reenviar SMTP incerto

            return;
        }

        Log::info('E-mail de notificação de inscrição enviado.', $context + ['email_dispatch_id' => $dispatch->id]);

        $msg->ack();
    }
}
