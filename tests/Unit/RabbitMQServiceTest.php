<?php

namespace Tests\Unit;

use App\Services\RabbitMQService;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use Tests\TestCase;

class RabbitMQServiceTest extends TestCase
{
    public function test_abre_conexao_apenas_ao_consumir_e_aplica_prefetch_configurado(): void
    {
        config(['rabbitmq.enabled' => false, 'rabbitmq.prefetch_count' => 25]);
        $channel = Mockery::mock(AMQPChannel::class);
        $channel->shouldReceive('exchange_declare')->once();
        $channel->shouldReceive('queue_declare')->once();
        $channel->shouldReceive('queue_bind')->once();
        $channel->shouldReceive('basic_qos')->once()->with(0, 25, false);
        $channel->shouldReceive('basic_consume')->once();
        $channel->shouldReceive('is_consuming')->once()->andReturnFalse();
        $channel->shouldReceive('close')->once();

        $connection = Mockery::mock(AMQPStreamConnection::class);
        $connection->shouldReceive('channel')->once()->andReturn($channel);
        $connection->shouldReceive('close')->once();

        $service = new class($connection) extends RabbitMQService
        {
            public int $connectionAttempts = 0;

            public function __construct(private AMQPStreamConnection $connectionMock) {}

            protected function openConnection(): AMQPStreamConnection
            {
                $this->connectionAttempts++;

                return $this->connectionMock;
            }
        };

        $service->consume('queue', 'exchange', 'route', static function (): void {});
        $this->assertSame(0, $service->connectionAttempts);

        config(['rabbitmq.enabled' => true]);

        $service->consume('queue', 'exchange', 'route', static function (): void {});
        $this->assertSame(1, $service->connectionAttempts);
    }

    public function test_falha_de_conexao_so_e_propagada_ao_iniciar_consumo(): void
    {
        config(['rabbitmq.enabled' => true]);

        $service = new class extends RabbitMQService
        {
            protected function openConnection(): AMQPStreamConnection
            {
                throw new RuntimeException('Broker indisponível');
            }
        };

        $this->expectExceptionMessage('Falha ao conectar ao RabbitMQ.');

        $service->consume('queue', 'exchange', 'route', static function (): void {});
    }
}
