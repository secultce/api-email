<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class RabbitMQService
{
    private $connection;
    private $channel;

    public function __construct()
    {
        $this->connect();
    }

    private function connect(): bool
    {
        // Evitar conexão em desenvolvimento se RabbitMQ não estiver configurado
        if (env('APP_ENV') === 'local' && !env('RABBITMQ_ENABLED', false)) {
            Log::info('RabbitMQ connection skipped in local environment');
            return false;
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
            Log::info('Conexão com RabbitMQ estabelecida com sucesso');
            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao conectar ao RabbitMQ: ' . $e->getMessage(), [
                'host' => config('rabbitmq.host', 'localhost'),
                'port' => config('rabbitmq.port', 5672),
                'user' => config('rabbitmq.user', 'guest'),
                'vhost' => config('rabbitmq.vhost', '/')
            ]);
            $this->connection = null;
            $this->channel = null;
            return false;
        }
    }

    public function consume(string $queue, string $exchange, string $routingKey, callable $callback): void
    {
        if (!$this->channel) {
            Log::error('Não foi possível consumir mensagens: canal do RabbitMQ não inicializado', [
                'queue' => $queue,
                'exchange' => $exchange,
                'routingKey' => $routingKey
            ]);
            return;
        }
        try {
            // Declarar a exchange
            $this->channel->exchange_declare($exchange, 'direct', false, true, false);

            // Declarar a fila
            $this->channel->queue_declare($queue, false, true, false, false);

            // Vincular a fila à exchange
            $this->channel->queue_bind($queue, $exchange, $routingKey);

            // Consumir mensagens
            $this->channel->basic_consume($queue, '', false, false, false, false, function ($msg) use ($callback) {
                $callback($msg);
            });

            Log::info("Consumindo mensagens da fila: {$queue}");
            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            Log::error('Erro ao consumir mensagem do RabbitMQ: ' . $e->getMessage(), [
                'queue' => $queue,
                'exchange' => $exchange,
                'routingKey' => $routingKey
            ]);
        }
    }

    public function __destruct()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            Log::error('Erro ao fechar conexão com RabbitMQ: ' . $e->getMessage());
        }
    }
}
