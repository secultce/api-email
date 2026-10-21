<?php

namespace App\Services;

use App\Mail\ImporteRegistrationMail;
use App\Models\EmailDispatch;
use App\Models\ImportRegistrationEmailProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportRegistrationProcessor
{
    /**
     * Processa uma inscrição de forma idempotente.
     */
    public function process(array $data): void
    {
        $validator = Validator::make($data, [
            'event_type' => [
                'sometimes',
                'string',
                'in:registration.import_requested',
            ],
            'schema_version' => [
                'sometimes',
                'integer',
                'in:1',
            ],
            'occurred_at' => [
                'sometimes',
                'date',
            ],
            'message_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
            ],
            'registration' => [
                'required',
                'integer',
                'min:1',
            ],
            'opportunity_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'opportunity_name' => [
                'required',
                'string',
            ],
            'number' => [
                'required',
            ],
            'agent_name' => [
                'required',
                'string',
            ],
            'agent_email' => [
                'required',
                'email',
            ],
        ]);

        if ($validator->fails()) {
            throw new InvalidRegistrationMessageException($validator->errors()->toJson());
        }

        $messageId = trim((string) ($data['message_id'] ?? ''));

        if ($messageId === '') {
            $messageId = (string) Str::uuid();
        }

        /*
         * Reserva a combinação oportunidade/inscrição no banco.
         *
         * Registros sent, sending e unknown não podem iniciar
         * outro envio automaticamente.
         *
         * Registros pending e failed podem ser processados.
         */
        $process = $this->reserveProcess($data, $messageId);

        if (!$process) {
            Log::warning('Mensagem duplicada ou já processada.', [
                'opportunity_id' => $data['opportunity_id'],
                'registration' => $data['registration'],
                'message_id' => $messageId,
            ]);

            return;
        }

        $dispatch = null;

        /*
         * Prepara e registra a auditoria antes de tentar enviar.
         *
         * Uma falha nesta etapa é considerada segura para tentar
         * novamente, porque Mail::send() ainda não foi chamado.
         */
        try {
            $mailable = new ImporteRegistrationMail($data);

            $subject = $mailable->envelope()->subject;

            $content = $mailable->render();

            $dispatch = EmailDispatch::create([
                'to' => $data['agent_email'],
                'subject' => $subject,
                'content' => $content,
                'mailable_type' => ImporteRegistrationMail::class,
                'meta' => [
                    'registration' => $data['registration'],
                    'opportunity_id' => $data['opportunity_id'],
                    'opportunity_name' => $data['opportunity_name'],
                    'number' => $data['number'],
                    'agent_name' => $data['agent_name'],
                    'agent_email' => $data['agent_email'],
                    'message_id' => $messageId,
                ],
                'status' => 'pending',
                'attempted_at' => null,
                'dispatched_at' => null,
                'failed_at' => null,
                'error_message' => null,
            ]);

            /*
             * Marca o início da tentativa imediatamente antes
             * da chamada ao serviço de e-mail.
             */
            $dispatch->status = 'sending';
            $dispatch->attempted_at = now();
            $dispatch->save();
        } catch (Throwable $e) {
            $this->markFailed($process, $dispatch, $e);

            throw $e;
        }

        /*
         * Tenta enviar o e-mail e registrar o resultado.
         *
         * A partir deste ponto, uma exceção pode representar
         * um envio que foi aceito pelo SMTP sem confirmação
         * recebida pela aplicação.
         */
        try {
            Mail::to($data['agent_email'])->send($mailable);

            DB::transaction(function () use ($process, $dispatch) {
                $processRecord = ImportRegistrationEmailProcess::query()
                    ->whereKey($process->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $processRecord->status = 'sent';
                $processRecord->sent_at = now();
                $processRecord->failed_at = null;
                $processRecord->last_error = null;
                $processRecord->save();

                $dispatchRecord = EmailDispatch::query()
                    ->whereKey($dispatch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $dispatchRecord->status = 'sent';
                $dispatchRecord->dispatched_at = now();
                $dispatchRecord->failed_at = null;
                $dispatchRecord->error_message = null;
                $dispatchRecord->save();
            });

            Log::info('E-mail aceito pelo serviço de envio.', [
                'opportunity_id' => $data['opportunity_id'],
                'registration' => $data['registration'],
                'email_dispatch_id' => $dispatch->id,
                'message_id' => $messageId,
            ]);
        } catch (Throwable $e) {
            $this->markUnknown($process, $dispatch, $e);

            throw new EmailDeliveryOutcomeUnknownException('O envio terminou com resultado desconhecido.', 0, $e);
        }
    }

    /**
     * Reserva o processamento de maneira segura para concorrência.
     *
     * A restrição UNIQUE no banco é indispensável.
     */
    private function reserveProcess(array $data, string $messageId): ?ImportRegistrationEmailProcess
    {
        return DB::transaction(function () use ($data, $messageId) {
            $now = now();

            /*
             * Insere um registro inicial se a combinação ainda não
             * existir. Se outro consumidor já criou o registro,
             * a restrição UNIQUE impede uma segunda inserção.
             */
            DB::table('import_registration_email_processes')
                ->insertOrIgnore([
                    'opportunity_id' => $data['opportunity_id'],
                    'registration_id' => $data['registration'],
                    'status' => 'pending',
                    'attempts' => 0,
                    'message_id' => $messageId,
                    'agent_email' => $data['agent_email'],
                    'started_at' => null,
                    'sent_at' => null,
                    'failed_at' => null,
                    'last_error' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            /*
             * Bloqueia a linha para serializar as alterações
             * de estado da mesma inscrição.
             */
            $process = ImportRegistrationEmailProcess::query()
                ->where('opportunity_id', $data['opportunity_id'])
                ->where('registration_id', $data['registration'])
                ->lockForUpdate()
                ->first();

            if (!$process) {
                throw new RuntimeException('Não foi possível reservar o processamento da inscrição.');
            }

            /*
             * Nunca reenviar automaticamente quando o resultado
             * anterior foi enviado, está em andamento ou é incerto.
             */
            if (!in_array(
                $process->status,
                ['pending', 'failed'],
                true
            )) {
                return null;
            }

            $process->status = 'sending';
            $process->attempts = ((int) $process->attempts) + 1;
            $process->message_id = $messageId;
            $process->agent_email = $data['agent_email'];
            $process->started_at = now();
            $process->sent_at = null;
            $process->failed_at = null;
            $process->last_error = null;
            $process->save();

            return $process;
        });
    }

    /**
     * Registra uma falha ocorrida antes da tentativa de envio.
     *
     * Como o envio ainda não começou, o processamento poderá
     * ser reexecutado posteriormente.
     */
    private function markFailed(
        ImportRegistrationEmailProcess $process,
        ?EmailDispatch $dispatch,
        Throwable $exception
    ): void {
        try {
            DB::transaction(function () use ($process, $dispatch, $exception) {
                $processRecord = ImportRegistrationEmailProcess::query()
                    ->whereKey($process->id)
                    ->lockForUpdate()
                    ->first();

                if ($processRecord) {
                    $processRecord->status = 'failed';
                    $processRecord->failed_at = now();
                    $processRecord->last_error = Str::limit($exception->getMessage(), 2000);
                    $processRecord->save();
                }

                if ($dispatch) {
                    $dispatchRecord = EmailDispatch::query()
                        ->whereKey($dispatch->id)
                        ->lockForUpdate()
                        ->first();

                    if ($dispatchRecord) {
                        $dispatchRecord->status = 'failed';
                        $dispatchRecord->failed_at = now();
                        $dispatchRecord->error_message = Str::limit($exception->getMessage(), 2000);
                        $dispatchRecord->save();
                    }
                }
            });
        } catch (Throwable $databaseException) {
            Log::critical(
                'Não foi possível registrar a falha anterior ao envio.',
                [
                    'process_id' => $process->id,
                    'dispatch_id' => $dispatch?->id,
                    'exception' => $exception->getMessage(),
                    'database_exception' => $databaseException->getMessage(),
                ]
            );
        }
    }

    /**
     * Registra um resultado de envio que não pode ser confirmado.
     *
     * Não autoriza um novo envio automático.
     */
    private function markUnknown(
        ImportRegistrationEmailProcess $process,
        EmailDispatch $dispatch,
        Throwable $exception
    ): void {
        try {
            DB::transaction(function () use ($process, $dispatch, $exception) {
                $processRecord = ImportRegistrationEmailProcess::query()
                    ->whereKey($process->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $processRecord->status = 'unknown';
                $processRecord->last_error = Str::limit($exception->getMessage(), 2000);
                $processRecord->save();

                $dispatchRecord = EmailDispatch::query()
                    ->whereKey($dispatch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $dispatchRecord->status = 'unknown';
                $dispatchRecord->error_message = Str::limit($exception->getMessage(), 2000);
                $dispatchRecord->save();
            });
        } catch (Throwable $databaseException) {
            Log::critical(
                'Não foi possível registrar o resultado desconhecido do envio.',
                [
                    'process_id' => $process->id,
                    'dispatch_id' => $dispatch->id,
                    'exception' => $exception->getMessage(),
                    'database_exception' => $databaseException->getMessage(),
                ]
            );
        }
    }
}
