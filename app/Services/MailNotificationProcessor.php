<?php

namespace App\Services;

use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Envia os e-mails de inscrição iniciada/enviada já renderizados pelo Mapas Culturais.
 */
class MailNotificationProcessor
{
    /**
     * @throws InvalidRegistrationMessageException mensagem com dados inválidos
     * @throws Throwable falha no envio (o dispatch fica como failed)
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
        if ($this->alreadyProcessed($data['message_id'])) {
            Log::warning('Mensagem duplicada ou já processada.', $context);

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
            $dispatch?->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('E-mail de notificação de inscrição enviado.', $context + ['email_dispatch_id' => $dispatch->id]);
    }

    private function alreadyProcessed(string $messageId): bool
    {
        return EmailDispatch::query()
            ->where('mailable_type', MailNotification::class)
            ->where('meta->message_id', $messageId)
            ->whereIn('status', ['sending', 'sent'])
            ->exists();
    }
}
