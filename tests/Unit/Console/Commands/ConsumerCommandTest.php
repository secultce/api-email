<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ConsumerCommand;
use App\Mail\AnswerNotification;
use App\Mail\EmailRegistrationOpp;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsumerCommandTest extends TestCase
{
    public function test_command_starts_and_runs_correctly(): void
    {
        $process = new Process(['php', 'artisan', 'diligence:consumer']);
        $process->start();

        sleep(1);
        $this->assertTrue($process->isRunning());

        $process->signal(SIGINT);

        // Give it a moment to process the signal
        sleep(1);

        $this->assertFalse($process->isRunning());
        $this->assertEquals(130, $process->getExitCode());
    }

    public function test_process_message_for_prop_route()
    {
        Mail::fake();

        // Create a mock AMQPMessage for prop route
        $msgMock = $this->createMockAMQPMessage(
            json_encode([
                'email' => 'test@example.com',
                'name' => 'Test User',
                'number' => '12345',
                'days' => 30,
            ]),
            config('app.rabbitmq.route_key_prop'),
        );

        // Create a partial mock of the command
        $command = new ConsumerCommand;

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert mail was sent
        Mail::assertSent(EmailRegistrationOpp::class);
    }

    public function test_process_message_for_adm_route()
    {
        Mail::fake();

        // Create a mock AMQPMessage for adm route
        $msgMock = $this->createMockAMQPMessage(
            json_encode([
                'comission' => 'comission@example.com',
                'owner' => 'owner@example.com',
                'registration' => '54321',
            ]),
            config('app.rabbitmq.route_key_adm')
        );

        // Create a partial mock of the command
        $command = new ConsumerCommand;

        // Use reflection to call the protected method
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert mail was sent
        Mail::assertSent(AnswerNotification::class);
    }

    /**
     * Create a mock AMQPMessage for testing
     */
    private function createMockAMQPMessage(string $body, string $routingKey): MockInterface
    {
        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('basic_ack')
            ->once()
            ->andReturnNull();

        $msgMock = Mockery::mock(AMQPMessage::class);
        $msgMock->body = $body;
        $msgMock->shouldReceive('getRoutingKey')
            ->andReturn($routingKey);
        $msgMock->delivery_info = [
            'channel' => $channelMock,
            'delivery_tag' => 1,
        ];

        return $msgMock;
    }
}
