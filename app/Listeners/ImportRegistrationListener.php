<?php

namespace App\Listeners;

use App\Events\ImportRegistrationEvent;
use App\Mail\ImporteRegistrationMail;
use App\Models\EmailDispatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ImportRegistrationListener
{
    public function handle(ImportRegistrationEvent $event): void
    {
        $registration = $event->registration;

        /*
         * A validação principal já deve ter ocorrido no consumidor.
         * O listener continua verificando os dados como proteção adicional.
         */
        if (!$this->isValidRegistration($registration)) {
            throw new RuntimeException('Dados inválidos recebidos pelo listener de importação.');
        }

        /*
         * Usa a chave enviada pelo produtor, se existir.
         * Para mensagens antigas, deriva uma chave de negócio.
         */
        $idempotencyKey = $registration['idempotency_key']
            ?? sprintf(
                'import-registration-draft:%s:%s',
                $registration['opp_id'],
                $registration['registration']
            );

        $mailable = new ImporteRegistrationMail($registration);

        $meta = [
            'message_id' => $registration['message_id'] ?? null,
            'registration' => $registration['registration'],
            'opp_id' => $registration['opp_id'],
            'opp_name' => $registration['opp_name'],
            'number' => $registration['number'],
            'agent_name' => $registration['agent_name'],
            'agent_email' => $registration['agent_email'],
        ];

        /*
         * A restrição UNIQUE no banco é necessária para impedir
         * registros concorrentes com a mesma chave.
         */
        try {
            $dispatch = EmailDispatch::firstOrCreate(
                [
                    'idempotency_key' => $idempotencyKey,
                ],
                [
                    'to' => $registration['agent_email'],
                    'subject' => $mailable->envelope()->subject,
                    'content' => $mailable->render(),
                    'mailable_type' => ImporteRegistrationMail::class,
                    'meta' => $meta,
                    'status' => 'pending',
                    'attempts' => 0,
                    'last_error' => null,
                    'sent_at' => null,
                    'dispatched_at' => null,
                ]
            );
        } catch (QueryException $e) {
            /*
             * Uma inserção concorrente pode ter criado o registro
             * entre a consulta e a tentativa de inserção.
             *
             * Se não existir registro correspondente, a exceção
             * original precisa continuar sendo propagada.
             */
            $dispatch = EmailDispatch::where('idempotency_key', $idempotencyKey)->first();

            if (!$dispatch) {
                throw $e;
            }
        }

        /*
         * Se já houve um envio concluído, não envia novamente.
         */
        if ($dispatch->status === 'sent' || $dispatch->sent_at !== null) {
            Log::info(
                'Envio duplicado ignorado.',
                [
                    'idempotency_key' => $idempotencyKey,
                    'dispatch_id' => $dispatch->getKey(),
                ]
            );

            return;
        }

        /*
         * Tenta reservar o processamento atomicamente.
         *
         * Somente um consumidor consegue alterar o registro
         * de pending para sending.
         */
        $claimed = EmailDispatch::query()
            ->whereKey($dispatch->getKey())
            ->where('status', 'pending')
            ->update([
                'status' => 'sending',
                'attempts' => DB::raw('attempts + 1'),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            $dispatch->refresh();

            if ($dispatch->status === 'sent' || $dispatch->sent_at !== null) {
                return;
            }

            /*
             * Não reinicia automaticamente um envio que já esteja
             * sendo processado ou que tenha terminado em estado
             * de falha. Esses casos precisam de recuperação explícita.
             */
            throw new RuntimeException(
                sprintf(
                    'O envio %s não pode ser reservado. Status atual: %s.',
                    $dispatch->getKey(),
                    $dispatch->status
                )
            );
        }

        Log::info(
            'Iniciando envio de e-mail.',
            [
                'dispatch_id' => $dispatch->getKey(),
                'registration' => $registration['registration'],
                'agent_email' => $registration['agent_email'],
            ]
        );

        /*
         * Envia o e-mail.
         */
        try {
            Mail::to($registration['agent_email'])->send($mailable);
        } catch (Throwable $e) {
            /*
             * Registra a falha, mas não a transforma em sucesso.
             *
             * Uma nova tentativa deve considerar a possibilidade
             * de o transporte ter aceitado o e-mail antes da falha.
             */
            EmailDispatch::whereKey($dispatch->getKey())
                ->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($e->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);

            Log::error(
                'Falha na tentativa de envio de e-mail.',
                [
                    'dispatch_id' => $dispatch->getKey(),
                    'registration' => $registration['registration'],
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }

        /*
         * Atualiza a auditoria somente após o envio retornar
         * sem lançar uma exceção.
         */
        EmailDispatch::whereKey($dispatch->getKey())
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'dispatched_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        Log::info(
            'Envio de e-mail concluído.',
            [
                'dispatch_id' => $dispatch->getKey(),
                'registration' => $registration['registration'],
                'agent_email' => $registration['agent_email'],
            ]
        );
    }

    protected function isValidRegistration(array $registration): bool
    {
        return isset(
            $registration['registration'],
            $registration['opp_id'],
            $registration['opp_name'],
            $registration['number'],
            $registration['agent_name'],
            $registration['agent_email']
        )
            && is_numeric($registration['registration'])
            && is_numeric($registration['opp_id'])
            && is_string($registration['opp_name'])
            && is_scalar($registration['number'])
            && is_string($registration['agent_name'])
            && is_string($registration['agent_email'])
            && filter_var($registration['agent_email'], FILTER_VALIDATE_EMAIL) !== false;
    }
}
