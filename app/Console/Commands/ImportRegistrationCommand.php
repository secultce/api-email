<?php

namespace App\Console\Commands;

use App\Services\EmailDeliveryOutcomeUnknownException;
use App\Services\ImportRegistrationProcessor;
use App\Services\ImportRegistrationRetryPublisher;
use App\Services\InvalidRegistrationMessageException;
use App\Services\RabbitMQService;
use App\Services\RetryableImportRegistrationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class ImportRegistrationCommand extends Command
{
    protected $signature = 'rabbitmq:import-registration-command';

    protected $description = 'Consome mensagens de importação de inscrições';

    public function __construct(
        protected RabbitMQService $queueService,
        protected ImportRegistrationProcessor $processor,
        protected ImportRegistrationRetryPublisher $retryPublisher
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
        $retryCount = 0;

        try {
            // Lê o contador antes de efeitos colaterais; cabeçalho malformado é inválido.
            $retryCount = $this->readRetryCount($msg);

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
                'opportunity_id' => $data['opportunity_id'] ?? null,
                'registration_id' => $data['registration'] ?? null,
                'retry_count' => $retryCount,
            ];

            $this->processor->process($data);
        } catch (InvalidRegistrationMessageException $e) {
            Log::error('Mensagem de inscrição inválida.', $context + ['exception' => $e->getMessage()]);

            $msg->nack(false, false); // DLQ definitiva

            return;
        } catch (EmailDeliveryOutcomeUnknownException $e) {
            Log::critical('O resultado do envio do e-mail é desconhecido.', $context + [
                'exception' => $e->getMessage(),
            ]);

            report($e);
            // O status unknown foi persistido; o ACK fica após os catches.
        } catch (RetryableImportRegistrationException $e) {
            $routes = config('rabbitmq.retry.routes', []);
            $nextRetry = $retryCount + 1;

            if (!config('rabbitmq.retry.enabled', false) || !isset($routes[$nextRetry])) {
                Log::error('Tentativas de retry desabilitadas ou esgotadas; enviando à DLQ.', $context + [
                    'exception' => $e->getMessage(),
                    'retry_count' => $retryCount,
                ]);

                $msg->nack(false, false);

                return;
            }

            try {
                $this->retryPublisher->publish($body, $nextRetry);
            } catch (Throwable $publicationException) {
                Log::critical(
                    'Falha ao publicar na fila de retry; mensagem original permanece sem ACK.',
                    $context + ['exception' => $publicationException->getMessage()]
                );

                report($publicationException);

                // Propaga para o loop de consumo encerrar a conexão.
                // O RabbitMQ poderá reentregar a mensagem original.
                throw $publicationException;
            }

            Log::warning('Mensagem encaminhada para retry após falha anterior ao SMTP.', $context + [
                'next_retry' => $nextRetry,
            ]);

            $msg->ack(); // ACK somente depois do publisher confirm positivo.

            return;
        } catch (Throwable $e) {
            Log::error('Falha não recuperável automaticamente.', $context + [
                'exception' => $e->getMessage(),
            ]);

            report($e);

            $msg->nack(false, false); // DLQ; não reenviar SMTP incerto.

            return;
        }

        $msg->ack();
    }

    private function readRetryCount(AMQPMessage $msg): int
    {
        if (!$msg->has('application_headers')) {
            return 0;
        }

        $headers = $msg->get('application_headers');
        if (!$headers instanceof AMQPTable) {
            throw new InvalidRegistrationMessageException('Cabeçalho AMQP inválido.');
        }

        $data = $headers->getNativeData();
        $count = $data['x-import-registration-retry-count'] ?? 0;
        $max = count(config('rabbitmq.retry.routes', []));

        if (!is_int($count) || $count < 0 || $count > $max) {
            throw new InvalidRegistrationMessageException('Contador de retry inválido.');
        }

        return $count;
    }
}
