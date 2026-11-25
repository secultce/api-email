<?php

namespace Tests\Feature;

use App\Console\Commands\MailNotificationCommand;
use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Tests\TestCase;

class MailNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'message_id' => 'sendmailnotification:200' . uniqid(),
            'event' => 'registration.send',
            'template' => 'send_registration',
            'locale' => 'pt_BR',
            'registration_id' => 200,
            'to' => 'agente@example.com',
            'from' => 'naoresponda@example.com',
            'subject' => 'Inscrição enviada',
            'body' => '<p>Olá</p><a href="https://mapacultural.example.com/inscricao/200">Clique aqui</a>',
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function test_envia_email_com_html_recebido_e_registra_dispatch(): void
    {
        Mail::fake();
        $payload = $this->payload();

        $this->commandUnderTest()->accept($this->message(json_encode($payload), true));

        Mail::assertSent(MailNotification::class, function (MailNotification $mail) use ($payload) {
            return $mail->hasTo('agente@example.com')
                && $mail->hasSubject('Inscrição enviada')
                && str_contains($mail->render(), 'https://mapacultural.example.com/inscricao/200');
        });

        $this->assertDatabaseHas('email_dispatches', [
            'to' => 'agente@example.com',
            'status' => 'sent',
            'mailable_type' => MailNotification::class,
        ]);
    }

    public function test_mensagem_duplicada_nao_envia_de_novo(): void
    {
        Mail::fake();
        $body = json_encode($this->payload());
        $command = $this->commandUnderTest();

        $command->accept($this->message($body, true));
        $command->accept($this->message($body, true));

        Mail::assertSent(MailNotification::class, 1);
    }

    public function test_mensagem_invalida_vai_para_dlq(): void
    {
        Mail::fake();

        $this->commandUnderTest()->accept($this->message(json_encode($this->payload(['to' => 'invalido'])), false));
        $this->commandUnderTest()->accept($this->message('nao e json', false));

        Mail::assertNothingSent();
    }

    public function test_falha_no_envio_marca_failed_e_vai_para_dlq(): void
    {
        Mail::shouldReceive('to')->once()->with('agente@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Timeout SMTP'));

        $this->commandUnderTest()->accept($this->message(json_encode($this->payload()), false));

        $this->assertSame('failed', EmailDispatch::first()->status);
    }

    private function message(string $body, bool $shouldAck): AMQPMessage
    {
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);

        if ($shouldAck) {
            $message->shouldReceive('ack')->once();
            $message->shouldNotReceive('nack');
        } else {
            $message->shouldReceive('nack')->once()->with(false, false);
            $message->shouldNotReceive('ack');
        }

        return $message;
    }

    private function commandUnderTest(): MailNotificationCommand
    {
        // O mock evita conexão real com o RabbitMQ.
        $rabbit = Mockery::mock(RabbitMQService::class);

        return new class($rabbit) extends MailNotificationCommand {
            public function accept(AMQPMessage $message): void
            {
                $this->processMessage($message);
            }
        };
    }
}
