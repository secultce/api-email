<?php

namespace Tests\Unit;

use App\Services\ImportRegistrationRetryPublisher;
use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Tests\TestCase;

class ImportRegistrationRetryPublisherTest extends TestCase
{
    public function test_publica_mensagem_persistente_com_contador_e_confirmacao(): void
    {
        config()->set('rabbitmq.retry.exchange', 'exchange_notification_retry');
        config()->set('rabbitmq.retry.routes', [1 => 'module_import_registration_draft_retry_1']);

        $ackCallback = null;
        $channel = Mockery::mock(AMQPChannel::class);
        $connection = Mockery::mock(AMQPStreamConnection::class);
        $connection->shouldReceive('channel')->once()->andReturn($channel);
        $connection->shouldReceive('close')->once();
        $channel->shouldReceive('close')->once();
        $channel->shouldReceive('set_ack_handler')->once()->withArgs(function ($cb) use (&$ackCallback): bool {
            $ackCallback = $cb;
            return true;
        });
        $channel->shouldReceive('set_nack_handler')->once();
        $channel->shouldReceive('set_return_listener')->once();
        $channel->shouldReceive('confirm_select')->once();
        $channel->shouldReceive('basic_publish')->once()->withArgs(function (
            AMQPMessage $msg,
            $exchange,
            $route,
            $mandatory
        ): bool {
            $this->assertSame('{"registration":200}', $msg->getBody());
            $this->assertSame('exchange_notification_retry', $exchange);
            $this->assertSame('module_import_registration_draft_retry_1', $route);
            $this->assertTrue($mandatory);
            $this->assertSame(AMQPMessage::DELIVERY_MODE_PERSISTENT, $msg->get('delivery_mode'));
            $this->assertSame('application/json', $msg->get('content_type'));
            $headers = $msg->get('application_headers');
            $this->assertInstanceOf(AMQPTable::class, $headers);
            $this->assertSame(1, $headers->getNativeData()['x-import-registration-retry-count']);
            return true;
        });
        $channel->shouldReceive('wait_for_pending_acks_returns')->once()->with(10)
            ->andReturnUsing(function () use (&$ackCallback): void {
                $ackCallback(new AMQPMessage('ok'));
            });

        $this->makePublisher($connection)->publish('{"registration":200}', 1);
    }

    public function test_sem_confirmacao_nao_autoriza_ack_da_mensagem_original(): void
    {
        config()->set('rabbitmq.retry.exchange', 'exchange_notification_retry');
        config()->set('rabbitmq.retry.routes', [1 => 'module_import_registration_draft_retry_1']);

        $channel = Mockery::mock(AMQPChannel::class);
        $connection = Mockery::mock(AMQPStreamConnection::class);
        $connection->shouldReceive('channel')->once()->andReturn($channel);
        $connection->shouldReceive('close')->once();
        $channel->shouldReceive('close')->once();
        $channel->shouldReceive('set_ack_handler')->once();
        $channel->shouldReceive('set_nack_handler')->once();
        $channel->shouldReceive('set_return_listener')->once();
        $channel->shouldReceive('confirm_select')->once();
        $channel->shouldReceive('basic_publish')->once();
        $channel->shouldReceive('wait_for_pending_acks_returns')->once()->with(10);

        $this->expectException(RuntimeException::class);
        $this->makePublisher($connection)->publish('corpo', 1);
    }

    public function test_mandatory_sem_rota_e_rejeitado_mesmo_com_confirmacao_do_broker(): void
    {
        config()->set('rabbitmq.retry.exchange', 'exchange_notification_retry');
        config()->set('rabbitmq.retry.routes', [1 => 'module_import_registration_draft_retry_1']);

        $ack = null;
        $returned = null;
        $channel = Mockery::mock(AMQPChannel::class);
        $connection = Mockery::mock(AMQPStreamConnection::class);
        $connection->shouldReceive('channel')->once()->andReturn($channel);
        $connection->shouldReceive('close')->once();
        $channel->shouldReceive('close')->once();
        $channel->shouldReceive('set_ack_handler')->once()->withArgs(function ($cb) use (&$ack): bool {
            $ack = $cb;
            return true;
        });
        $channel->shouldReceive('set_return_listener')->once()->withArgs(function ($cb) use (&$returned): bool {
            $returned = $cb;
            return true;
        });
        $channel->shouldReceive('set_nack_handler')->once();
        $channel->shouldReceive('confirm_select')->once();
        $channel->shouldReceive('basic_publish')->once();
        $channel->shouldReceive('wait_for_pending_acks_returns')->once()->with(10)
            ->andReturnUsing(function () use (&$ack, &$returned): void {
                $returned(
                    312,
                    'NO_ROUTE',
                    'exchange_notification_retry',
                    'module_import_registration_draft_retry_1',
                    new AMQPMessage('corpo')
                );
                $ack(new AMQPMessage('corpo'));
            });

        $this->expectException(RuntimeException::class);
        $this->makePublisher($connection)->publish('corpo', 1);
    }

    public function test_rejeita_rota_nao_configurada_sem_conectar_ao_broker(): void
    {
        config()->set('rabbitmq.retry.routes', [1 => 'primeiro_retry']);
        $this->expectException(RuntimeException::class);

        $publisher = new class extends ImportRegistrationRetryPublisher {
            protected function openConnection(): AMQPStreamConnection
            {
                throw new RuntimeException('Não deveria tentar conectar');
            }
        };
        $publisher->publish('corpo', 3);
    }

    private function makePublisher(AMQPStreamConnection $connection): ImportRegistrationRetryPublisher
    {
        return new class($connection) extends ImportRegistrationRetryPublisher {
            public function __construct(private AMQPStreamConnection $connectionMock) {}

            protected function openConnection(): AMQPStreamConnection
            {
                return $this->connectionMock;
            }
        };
    }
}
