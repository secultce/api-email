<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

class RabbitMQService
{
    private $connection = null;

    private $channel = null;

    public function __construct()
    {
        if (!config('rabbitmq.enabled', true)) {
            Log::info('Conexão RabbitMQ não iniciada: integração desabilitada.');

            return;
        }

        try {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.pass'),
                config('rabbitmq.vhost')
            );

            $this->channel = $this->connection->channel();

            Log::info('Conexão com RabbitMQ estabelecida com sucesso.');
        } catch (Throwable $e) {
            Log::critical(
                'Não foi possível estabelecer conexão com RabbitMQ.',
                [
                    'host' => config('rabbitmq.host'),
                    'port' => config('rabbitmq.port'),
                    'vhost' => config('rabbitmq.vhost'),
                    'exception' => $e->getMessage(),
                ]
            );

            $this->close();

            /*
             * Não deixar a aplicação prosseguir como se
             * o consumidor estivesse funcionando.
             */
            throw new RuntimeException('Falha ao conectar ao RabbitMQ.', 0, $e);
        }
    }

    public function consume(
        string $queue,
        string $exchange,
        string $routingKey,
        callable $callback
    ): void {
        if (!config('rabbitmq.enabled', true)) {
            Log::info('Consumo RabbitMQ ignorado: integração desabilitada.');

            return;
        }

        if (!$this->channel) {
            throw new RuntimeException('Não existe um canal RabbitMQ disponível para consumo.');
        }

        try {
            /*
             * A exchange precisa ter o mesmo tipo e as mesmas
             * propriedades utilizadas pelo produtor.
             */
            $this->channel->exchange_declare(
                $exchange,
                'direct',
                false,
                true,
                false
            );

            /*
             * A fila é durável.
             *
             * Configure a DLX por meio de uma policy no RabbitMQ,
             * conforme a seção de configuração abaixo.
             */
            $this->channel->queue_declare(
                $queue,
                false,
                true,
                false,
                false
            );

            $this->channel->queue_bind(
                $queue,
                $exchange,
                $routingKey
            );

            $prefetchCount = max(1, (int) config('rabbitmq.consumer.prefetch_count', 1));

            $this->channel->basic_qos(0, $prefetchCount, false);

            $this->channel->basic_consume(
                $queue,
                '',
                false,
                false,
                false,
                false,
                function (AMQPMessage $msg) use ($callback): void {
                    $callback($msg);
                }
            );

            Log::info(
                'Consumidor RabbitMQ iniciado.',
                [
                    'queue' => $queue,
                    'exchange' => $exchange,
                    'routing_key' => $routingKey,
                    'prefetch_count' => $prefetchCount,
                ]
            );

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (Throwable $e) {
            Log::critical(
                'Falha no consumidor RabbitMQ.',
                [
                    'queue' => $queue,
                    'exchange' => $exchange,
                    'routing_key' => $routingKey,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    private function close(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Erro ao fechar o canal RabbitMQ.',
                ['exception' => $e->getMessage()]
            );
        }

        try {
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (Throwable $e) {
            Log::warning(
                'Erro ao fechar a conexão RabbitMQ.',
                ['exception' => $e->getMessage()]
            );
        }

        $this->channel = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
