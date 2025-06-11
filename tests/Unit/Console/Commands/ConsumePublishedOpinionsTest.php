<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ConsumePublishedOpinions;
use App\Mail\PublishedOpinions;
use App\Mail\PublishedRecourse;
use App\Services\AmqpService;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsumePublishedOpinionsTest extends TestCase
{
    public function test_command_starts_and_runs_correctly(): void
    {
        $process = new Process(['php', 'artisan', 'rabbitmq:consume-published-opinions-emails']);
        $process->start();

        $this->assertTrue($process->isRunning());

        $process->signal(SIGINT);

        sleep(1);

        $this->assertFalse($process->isRunning());
        $this->assertEquals(130, $process->getExitCode());
    }

    public function test_send_email_true(): void
    {
        Mail::fake();

        $registration = [
            'url' => 'https://example.com',
            'number' => 'te-320998499',
            'agent' => [
                'name' => 'John Doe',
                'email' => 'john@doe.example',
            ],
        ];

        $opportunity = [
            'name' => 'John Doe',
            'url' => 'https://example.com',
        ];

        $command = new ConsumePublishedOpinions($this->createMock(AmqpService::class));
        $command->setOutput($this->createMock(OutputStyle::class));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('sendMail');
        $method->setAccessible(true);
        $method->invoke($command, $registration, $opportunity);

        Mail::assertSent(PublishedOpinions::class);
    }

    public function test_send_email_false(): void
    {
        Mail::fake();

        $registration = [
            'url' => 'https://example.com',
            'agent' => [
                'name' => 'John Doe',
                'email' => 'john@doe.example',
            ],
        ];

        $opportunity = [
            'url' => 'https://example.com',
        ];

        $command = new ConsumePublishedOpinions($this->createMock(AmqpService::class));
        $command->setOutput($this->createMock(OutputStyle::class));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('sendMail');
        $method->setAccessible(true);
        $send = $method->invoke($command, $registration, $opportunity);

        // Asserts that the email was not sent
        $this->assertFalse($send);
        Mail::assertNotSent(PublishedRecourse::class);
    }

    public function test_process_message_with_success(): void
    {
        Mail::fake();

        $body = json_encode([
            'registrations' => [[
                'url' => 'https://example.com',
                'number' => 'te-320998499',
                'agent' => [
                    'name' => 'John Doe',
                    'email' => 'john@doe.example',
                ],
            ]],
            'opportunity' => [
                'name' => 'John Doe',
                'url' => 'https://example.com',
            ],
        ]);
        $msgMock = $this->createMockAMQPMessage($body);
        $msgMock->shouldReceive('ack')
            ->once()
            ->andReturnNull();
        $msgMock->shouldReceive('getBody')
            ->once()
            ->andReturn($body);

        $command = new ConsumePublishedOpinions($this->createMock(AmqpService::class));
        $command->setOutput($this->createMock(OutputStyle::class));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        Mail::assertSent(PublishedOpinions::class);
    }

    private function createMockAMQPMessage(string $body): MockInterface
    {
        $channelMock = Mockery::mock(AMQPChannel::class);

        $msgMock = Mockery::mock(AMQPMessage::class);
        $reflection = new \ReflectionClass(AMQPMessage::class);
        $reflection->getMethod('setBody')->invoke($msgMock, $body);
        $reflection->getMethod('setDeliveryTag')->invoke($msgMock, 1);
        $reflection->getMethod('setChannel')->invoke($msgMock, $channelMock);

        return $msgMock;
    }
}
