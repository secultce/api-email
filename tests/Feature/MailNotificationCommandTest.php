<?php

namespace Tests\Feature;

use App\Console\Commands\MailNotificationCommand;
use App\Services\EmailDeliveryOutcomeUnknownException;
use App\Services\InvalidRegistrationMessageException;
use App\Services\MailNotificationProcessor;
use App\Services\RabbitMQService;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Tests\TestCase;

class MailNotificationCommandTest extends TestCase
{
    private function body(): string
    {
        return json_encode([
            'message_id' => 'sendmailnotification:200abc',
            'registration_id' => 200,
            'to' => 'agente@example.com',
            'subject' => 'Inscrição enviada',
            'body' => '<p>Olá</p>',
        ]);
    }

    public function test_sucesso_recebe_ack(): void
    {
        $processor = Mockery::mock(MailNotificationProcessor::class);
        $processor->shouldReceive('process')->once()->with(Mockery::on(
            fn (array $data) => $data['message_id'] === 'sendmailnotification:200abc'
        ));

        $this->commandUnderTest($processor)->accept($this->message($this->body(), true));
    }

    public function test_json_invalido_vai_para_dlq_sem_chamar_o_processor(): void
    {
        $processor = Mockery::mock(MailNotificationProcessor::class);
        $processor->shouldNotReceive('process');

        $this->commandUnderTest($processor)->accept($this->message('nao e json', false));
        $this->commandUnderTest($processor)->accept($this->message('"texto"', false));
    }

    public function test_dados_invalidos_vao_para_dlq(): void
    {
        $processor = Mockery::mock(MailNotificationProcessor::class);
        $processor->shouldReceive('process')->once()
            ->andThrow(new InvalidRegistrationMessageException('to inválido'));

        $this->commandUnderTest($processor)->accept($this->message($this->body(), false));
    }

    public function test_resultado_desconhecido_recebe_ack(): void
    {
        $processor = Mockery::mock(MailNotificationProcessor::class);
        $processor->shouldReceive('process')->once()
            ->andThrow(new EmailDeliveryOutcomeUnknownException('Timeout SMTP'));

        $this->commandUnderTest($processor)->accept($this->message($this->body(), true));
    }

    public function test_falha_de_banco_vai_para_dlq(): void
    {
        $processor = Mockery::mock(MailNotificationProcessor::class);
        $processor->shouldReceive('process')->once()->andThrow(new RuntimeException('Banco indisponível'));

        $this->commandUnderTest($processor)->accept($this->message($this->body(), false));
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

    private function commandUnderTest(MailNotificationProcessor $processor): MailNotificationCommand
    {
        // O mock evita conexão real com o RabbitMQ.
        $rabbit = Mockery::mock(RabbitMQService::class);

        return new class($rabbit, $processor) extends MailNotificationCommand {
            public function accept(AMQPMessage $message): void
            {
                $this->processMessage($message);
            }
        };
    }
}
