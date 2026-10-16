<?php

namespace App\Console\Commands;

use App\Events\ImportRegistrationEvent;
use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JsonException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ImportRegistrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:import-registration-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para consumir a fila de importação de inscrições';

    protected $queueService;

    public function __construct(RabbitMQService $queueService)
    {
        parent::__construct();

        $this->queueService = $queueService;
    }

    /**
     * Executa o comando no console.
     */
    public function handle()
    {
        $queue = config('rabbitmq.queues.queue_import_registration');
        $exchange = config('rabbitmq.exchange_default');
        $routingKey = config('rabbitmq.routing.module_import_registration_draft');

        $this->queueService->consume(
            $queue,
            $exchange,
            $routingKey,
            function (AMQPMessage $msg) {
                $this->processMessage($msg);
            }
        );
    }

    protected function processMessage(AMQPMessage $msg): void
    {
        $body = $msg->getBody();

        try {
            $registration = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->rejectInvalidMessage($msg, 'JSON inválido.');

            return;
        }

        if (!is_array($registration)) {
            $this->rejectInvalidMessage($msg, 'A mensagem não contém um objeto JSON válido.');

            return;
        }

        $validator = Validator::make($registration, [
            'registration' => [
                'required',
                'integer',
                'min:1',
            ],

            'opp_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'opp_name' => [
                'required',
                'string',
                'max:255',
            ],

            'number' => [
                'required',
            ],

            'agent_name' => [
                'required',
                'string',
                'max:255',
            ],

            'agent_email' => [
                'required',
                'email',
                'max:254',
            ],
        ]);

        $numberIsScalar = isset($registration['number']) && is_scalar($registration['number']) && !is_bool($registration['number']);

        if ($validator->fails() || !$numberIsScalar) {
            Log::warning(
                'Mensagem de importação inválida.',
                [
                    'registration' => $registration['registration'] ?? null,
                    'opp_id' => $registration['opp_id'] ?? null,
                    'invalid_fields' => $validator->errors()->keys(),
                ]
            );

            /*
            * Rejeita a mensagem sem solicitar reentrega imediata.
            *
            * IMPORTANTE:
            * A fila precisa estar configurada com uma DLX e uma DLQ.
            */
            $msg->reject(false);

            return;
        }

        try {
            event(new ImportRegistrationEvent($registration));
        } catch (Throwable $e) {
            \Sentry\captureException($e);

            Log::error(
                'Erro ao processar a mensagem de importação.',
                [
                    'registration' => $registration['registration'],
                    'opp_id' => $registration['opp_id'],
                    'exception' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ]
            );

            /*
            * Não reencaminha imediatamente para a mesma fila.
            *
            * Com uma DLX configurada, a mensagem será encaminhada
            * para o destino de falhas definido na infraestrutura.
            */
            $msg->nack(false);

            return;
        }

        $msg->ack();

        Log::info(
            'Mensagem processada com sucesso.',
            [
                'registration' => $registration['registration'],
                'opp_id' => $registration['opp_id'],
            ]
        );
    }

    /**
     * Rejeita uma mensagem inválida.
     */
    protected function rejectInvalidMessage(
        AMQPMessage $msg,
        string $reason
    ): void {
        Log::warning(
            'Mensagem inválida recebida do RabbitMQ.',
            [
                'reason' => $reason,
                'body_bytes' => strlen($msg->getBody()),
            ]
        );

        $msg->reject(false);
    }
}
