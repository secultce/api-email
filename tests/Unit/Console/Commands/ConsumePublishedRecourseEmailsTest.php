<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ConsumePublishedRecourseEmails;
use App\Mail\PublishedRecourse;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsumePublishedRecourseEmailsTest extends TestCase
{
    public function test_command_starts_and_runs_correctly(): void
    {
        $process = new Process(['php', 'artisan', 'rabbitmq:consume-published-recourse-emails']);
        $process->start();

        sleep(1);
        $this->assertTrue($process->isRunning());

        $process->signal(SIGINT);

        // Give it a moment to process the signal
        sleep(1);

        $this->assertFalse($process->isRunning());
        $this->assertEquals(130, $process->getExitCode());
    }

    public function test_send_email_true(): void
    {
        Mail::fake();

        // Create a mock AMQPMessage for prop route
        $msgMock = $this->createMockAMQPMessage(
            json_encode([
                'email' => 'test@email.test',
                'agentId' => 123456,
                'opportunityName' => 'Test Opportunity',
            ]),
        );

        $command = new ConsumePublishedRecourseEmails;

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('sendEmail');
        $method->setAccessible(true);
        $method->invoke($command, json_decode($msgMock->body, true));

        // Asserts that the email was not sent
        Mail::assertSent(PublishedRecourse::class);
    }

    public function test_send_email_false(): void
    {
        Mail::fake();

        // Create a mock AMQPMessage for prop route
        $msgMock = $this->createMockAMQPMessage(
            json_encode([
                'emal' => 'test@email.fail',
                'agent' => 123456,
            ]),
        );

        $command = new ConsumePublishedRecourseEmails;

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('sendEmail');
        $method->setAccessible(true);
        $send = $method->invoke($command, json_decode($msgMock->body, true));

        // Asserts that the email was not sent
        $this->assertFalse($send);
        Mail::assertNotSent(PublishedRecourse::class);
    }

    public function test_process_message_with_success(): void
    {
        Mail::fake();

        $msgMock = $this->createMockAMQPMessage(
            json_encode([
                'email' => 'test@example.com',
                'agentId' => 123456,
                'opportunityName' => 'Test Opportunity',
            ])
        );
        $msgMock->shouldReceive('ack')
            ->once()
            ->andReturnNull();

        $output = $this->createMock(OutputStyle::class);
        $command = new ConsumePublishedRecourseEmails;
        $command->setOutput($output);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        Mail::assertSent(PublishedRecourse::class);
    }

//    public function test_process_message_with_failure(): void
//    {
        // @TODO: Needs discovery
//    }

    private function createMockAMQPMessage(string $body): MockInterface
    {
        $channelMock = Mockery::mock(AMQPChannel::class);

        $msgMock = Mockery::mock(AMQPMessage::class);
        $msgMock->body = $body;
        $msgMock->delivery_info = [
            'channel' => $channelMock,
            'delivery_tag' => 1,
        ];

        return $msgMock;
    }
}
