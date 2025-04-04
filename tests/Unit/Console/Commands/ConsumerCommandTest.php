<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ConsumerCommand;
use App\Mail\AnswerNotification;
use App\Mail\EmailRegistrationOpp;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsumerCommandTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    #[DataProvider('provideRoutingKeys')]
    public function test_it_sends_opportunity_email_when_receives_routing_key(array $messageData, string $routingKey, string $mailable): void
    {
        Mail::fake();

        $mockMsg = $this->getMockBuilder(AMQPMessage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRoutingKey'])
            ->getMock();

        $mockMsg->method('getRoutingKey')
            ->willReturn($routingKey);

        $mockMsg->body = json_encode($messageData);

        $mockMsg->delivery_info = [
            'channel' => $this->createMockChannel(),
            'delivery_tag' => 1,
        ];

        $command = new ConsumerCommand;
        $this->callProtected($command, 'processMessage', [$mockMsg]);

        Mail::assertSent($mailable, function ($mail) use ($messageData) {
            return $mail->hasTo($messageData['email']) &&
                $mail->hasFrom(config('app.mail.from.address')) &&
                $mail->hasSubject('Diligência - Inscrição '.$messageData['number']);
        });
    }

    public static function provideRoutingKeys(): array
    {
        return [
            'prop_with_valid_message' => [
                'messageData' => [
                    'email' => 'test@example.com',
                    'name' => 'John Doe',
                    'number' => 'on-728279831',
                    'days' => 30,
                ],
                'routingKey' => config('app.rabbitmq.route_key_prop'),
                'mailable' => EmailRegistrationOpp::class,
            ],
            'prop_with_invalid_message' => [
                'messageData' => [
                    'email' => 'invalid-email',
                    'registration' => 'invalid-registration',
                    'name' => '',
                    'days' => -1,
                ],
                'routingKey' => config('app.rabbitmq.route_key_prop'),
                'mailable' => EmailRegistrationOpp::class,
            ],
            'adm_with_valid_message' => [
                'messageData' => [
                    'email' => 'test@example.com',
                    'name' => 'John Doe',
                    'number' => 'on-728279831',
                    'days' => 30,
                ],
                'routingKey' => config('app.rabbitmq.route_key_adm'),
                'mailable' => AnswerNotification::class,
            ],
            'adm_with_invalid_message' => [
                'messageData' => [
                    'email' => 'invalid-email',
                    'registration' => 'invalid-registration',
                    'name' => '',
                    'days' => -1,
                ],
                'routingKey' => config('app.rabbitmq.route_key_adm'),
                'mailable' => AnswerNotification::class,
            ],
        ];
    }

    /**
     * @throws \ReflectionException
     */
    protected function callProtected($object, $method, array $args = [])
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    private function createMockChannel(): AMQPChannel
    {
        $channel = $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $channel->expects($this->once())
            ->method('basic_ack');

        return $channel;
    }
}
