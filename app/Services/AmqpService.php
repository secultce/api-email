<?php

declare(strict_types=1);

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class AmqpService
{
    private AMQPStreamConnection $connection;

    private AMQPChannel $channel;

    /**
     * @throws \Exception
     */
    public function __construct()
    {

    }

    /**
     * @throws \Exception
     */
    public function consumeQueue(string $queue, callable $callback): void
    {
        $this->channel->queue_declare(queue: $queue, durable: true, auto_delete: false);

        $this->channel->basic_consume(queue: $queue, callback: $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }

        $this->channel->close();
        $this->connection->close();
    }
}
