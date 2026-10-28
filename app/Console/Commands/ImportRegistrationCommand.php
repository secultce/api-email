<?php

namespace App\Console\Commands;

use App\Services\EmailDeliveryOutcomeUnknownException;
use App\Services\ImportRegistrationProcessor;
use App\Services\InvalidRegistrationMessageException;
use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ImportRegistrationCommand extends Command
{
    protected $signature = 'rabbitmq:import-registration-command';

    protected $description = 'Consome mensagens de importação de inscrições';

    public function __construct(
        protected RabbitMQService $queueService,
        protected ImportRegistrationProcessor $processor
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('rabbitmq.enabled', true)) {
            Log::info('Consumidor de inscrições desabilitado pela configuração.');

            return self::SUCCESS;
        }

        $queue = config('rabbitmq.queues.queue_import_registration');
        $exchange = config('rabbitmq.exchange_default');
        $routingKey = config('rabbitmq.routing.module_import_registration_draft');

        try {
            $this->queueService->consume(
                $queue,
                $exchange,
                $routingKey,
                function (AMQPMessage $msg): void {
                    $this->processMessage($msg);
                }
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::critical(
                'O consumidor RabbitMQ foi encerrado por uma falha.',
                [
                    'exception' => $e->getMessage(),
                    'queue' => $queue,
                ]
            );

            report($e);

            return self::FAILURE;
        }
    }

    protected function processMessage(AMQPMessage $msg): void
    {
        try {
            try {
                $data = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidRegistrationMessageException('O corpo da mensagem não contém um JSON válido.', 0, $e);
            }

            if (!is_array($data)) {
                throw new InvalidRegistrationMessageException('A mensagem deve conter um objeto JSON.');
            }

            $this->processor->process($data);

            /*
            * O processamento terminou com sucesso ou a mensagem
            * foi identificada como duplicada.
            */
            $msg->ack();
        } catch (InvalidRegistrationMessageException $e) {
            Log::error(
                'Mensagem de inscrição inválida.',
                [
                    'exception' => $e->getMessage(),
                    'body' => $msg->getBody(),
                ]
            );

            /*
            * Descarta a entrega atual da fila principal.
            * Para não perder a mensagem, configure uma DLQ.
            */
            $msg->nack(false, false);
        } catch (EmailDeliveryOutcomeUnknownException $e) {
            Log::critical(
                'O resultado do envio do e-mail é desconhecido.',
                [
                    'exception' => $e->getMessage(),
                    'body' => $msg->getBody(),
                ]
            );

            /*
            * Não repetir um envio cujo resultado é incerto.
            * A reconciliação será feita pelo registro no banco.
            */
            report($e);

            $msg->ack();
        } catch (Throwable $e) {
            Log::error(
                'Falha no processamento da inscrição.',
                [
                    'exception' => $e->getMessage(),
                    'body' => $msg->getBody(),
                ]
            );

            report($e);

            /*
            * Rejeita a mensagem sem solicitar reentrega imediata.
            * Com uma DLX configurada, ela será encaminhada à DLQ.
            */
            $msg->nack(false, false);
        }
    }
}
