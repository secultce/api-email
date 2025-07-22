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

    private function connect(): void
    {
        try {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password'),
                config('rabbitmq.vhost')
            );
            $this->channel = $this->connection->channel();
        } catch (\Exception $e) {
            Log::error('Erro ao conectar ao RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    public function consume(string $queue, string $exchange, string $routingKey, callable $callback): void
    {
        try {
            // Declarar a exchange
            $this->channel->exchange_declare($exchange, 'direct', false, true, false);

            // Declarar a fila
            $this->channel->queue_declare($queue, false, true, false, false);

            // Vincular a fila à exchange
            $this->channel->queue_bind($queue, $exchange, $routingKey);

            // Consumir mensagens
            $this->channel->basic_consume($queue, '', false, true, false, false, function ($msg) use ($callback) {
                $callback($msg);
            });

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            Log::error('Erro ao consumir mensagem do RabbitMQ: ' . $e->getMessage());
            throw $e;
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
