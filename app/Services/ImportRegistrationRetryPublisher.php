<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Throwable;

/**
 * Publica uma cópia persistente em uma fila de retry.
 * Usa publisher confirms + mandatory para só confirmar o original
 * depois que o RabbitMQ aceitar a nova publicação e a rotear.
 */
class ImportRegistrationRetryPublisher
{
    public function publish(string $body, int $retryNumber): void
    {
        $routes = config('rabbitmq.retry.routes', []);
        $routingKey = $routes[$retryNumber] ?? null;
        $exchange = config('rabbitmq.retry.exchange');

        if (!$routingKey || !$exchange) {
            throw new RuntimeException('Rota de retry inexistente na configuração RabbitMQ.');
        }

        $connection = null;
        $channel = null;
        $confirmed = false;
        $returned = false;
        $nacked = false;

        try {
            $connection = $this->openConnection();
            $channel = $connection->channel();

            $channel->set_ack_handler(function (AMQPMessage $message) use (&$confirmed): void {
                $confirmed = true;
            });

            $channel->set_nack_handler(function (AMQPMessage $message) use (&$nacked): void {
                $nacked = true;
            });

            $channel->set_return_listener(function (
                $replyCode,
                $replyText,
                $returnedExchange,
                $returnedRoutingKey,
                AMQPMessage $message
            ) use (&$returned): void {
                $returned = true;
            });

            $channel->confirm_select();

            $retryMessage = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'application_headers' => new AMQPTable([
                    'x-import-registration-retry-count' => $retryNumber,
                ]),
            ]);

            // mandatory = true: se não houver binding/rota, o broker retorna a mensagem.
            $channel->basic_publish($retryMessage, $exchange, $routingKey, true);
            $channel->wait_for_pending_acks_returns(10);

            if (!$confirmed || $nacked || $returned) {
                throw new RuntimeException('RabbitMQ não confirmou o roteamento da mensagem para a fila de retry.');
            }
        } finally {
            // Não mascarar falha de publicação por erro de fechamento de conexão.
            if ($channel !== null) {
                try {
                    $channel->close();
                } catch (Throwable $ignored) {
                }
            }
            if ($connection !== null) {
                try {
                    $connection->close();
                } catch (Throwable $ignored) {
                }
            }
        }
    }

    /** Substituível nos testes; nenhuma conexão é aberta pelo construtor. */
    protected function openConnection(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            config('rabbitmq.host'),
            (int) config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password'),
            config('rabbitmq.vhost', '/')
        );
    }
}
