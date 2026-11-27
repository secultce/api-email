<?php

namespace App\Services;

use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envia os e-mails de inscrição iniciada/enviada já renderizados pelo Mapas Culturais.
 */
class MailNotificationProcessor
{
    /**
     * @throws InvalidRegistrationMessageException mensagem com dados inválidos
     * @throws EmailDeliveryOutcomeUnknownException envio com resultado incerto (status unknown)
     * @throws Throwable falha de banco antes do envio, ou ao registrar o resultado incerto
     */
    public function process(array $data): void
    {
        $validator = Validator::make($data, [
            'message_id' => ['required', 'string'],
            'event' => ['sometimes', 'string', 'in:registration.start,registration.send'],
            'registration_id' => ['required', 'integer', 'min:1'],
            'to' => ['required', 'email'],
            'subject' => ['required', 'string'],
            'body' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidRegistrationMessageException($validator->errors()->toJson());
        }

        $context = [
            'message_id' => $data['message_id'],
            'event' => $data['event'] ?? null,
            'registration_id' => $data['registration_id'],
        ];

        // O job do Mapas pode ser reprocessado e publicar a mesma mensagem de novo
        $dispatch = $this->reserve($data, $context);

        if (!$dispatch) {
            Log::warning('Mensagem duplicada ou já processada.', $context);

            return;
        }

        // A partir daqui uma exceção pode significar um e-mail aceito pelo SMTP
        try {
            Mail::to($data['to'])->send(new MailNotification($data['subject'], $data['body']));

            $dispatch->update([
                'status' => 'sent',
                'dispatched_at' => now(),
            ]);
        } catch (Throwable $e) {
            $this->markUnknown($dispatch, $e, $context);

            throw new EmailDeliveryOutcomeUnknownException('O envio terminou com resultado desconhecido.', 0, $e);
        }

        Log::info('E-mail de notificação de inscrição enviado.', $context + ['email_dispatch_id' => $dispatch->id]);
    }

    /**
     * Reserva o message_id de forma atômica (índice UNIQUE parcial).
     *
     * Retorna null quando a mensagem já foi enviada, está em envio ou
     * tem resultado incerto; nesses casos não há reenvio automático.
     */
    private function reserve(array $data, array $context): ?EmailDispatch
    {
        return DB::transaction(function () use ($data, $context) {
            $now = now();

            EmailDispatch::query()->insertOrIgnore([
                'to' => $data['to'],
                'subject' => $data['subject'],
                'content' => $data['body'],
                'mailable_type' => MailNotification::class,
                'meta' => json_encode($context),
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $dispatch = EmailDispatch::query()
                ->where('mailable_type', MailNotification::class)
                ->where('meta->message_id', $data['message_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($dispatch->status, ['pending', 'failed'], true)) {
                return null;
            }

            $dispatch->update([
                'status' => 'sending',
                'attempted_at' => $now,
                'failed_at' => null,
                'error_message' => null,
            ]);

            return $dispatch;
        });
    }

    private function markUnknown(EmailDispatch $dispatch, Throwable $exception, array $context): void
    {
        try {
            $dispatch->update([
                'status' => 'unknown',
                'error_message' => Str::limit($exception->getMessage(), 2000),
            ]);
        } catch (Throwable $databaseException) {
            Log::critical('Não foi possível registrar o resultado desconhecido do envio.', $context + [
                'email_dispatch_id' => $dispatch->id,
                'exception' => $exception->getMessage(),
                'database_exception' => $databaseException->getMessage(),
            ]);

            // O status sending continua bloqueando um segundo envio; a mensagem vai para a DLQ.
            throw $databaseException;
        }
    }
}
