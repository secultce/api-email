<?php

namespace Tests\Feature;

use App\Console\Commands\ImportRegistrationCommand;
use App\Mail\ImporteRegistrationMail;
use App\Models\ImportRegistrationEmailProcess;
use App\Services\ImportRegistrationProcessor;
use App\Services\ImportRegistrationRetryPublisher;
use App\Services\RetryableImportRegistrationException;
use App\Services\RabbitMQService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Tests\TestCase;

class ImportRegistrationCommandTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'message_id' => (string) Str::uuid(),
            'event_type' => 'registration.import_requested',
            'schema_version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'registration' => 200,
            'opportunity_id' => 100,
            'opportunity_name' => 'Oportunidade de teste',
            'number' => 'ON-123456',
            'agent_name' => 'Agente de teste',
            'agent_email' => 'agente@example.com',
        ];
    }

    public function test_sucesso_e_duplicata_recebem_ack(): void
    {
        Mail::fake();
        $data = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message1 = $this->message($data, true);
        $message2 = $this->message($data, true);

        $command = $this->commandUnderTest();
        $command->accept($message1);
        $command->accept($message2);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
    }

    public function test_falha_no_ack_nao_tenta_nack_da_mensagem_ja_processada(): void
    {
        Mail::fake();
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('has')->with('application_headers')->once()->andReturn(false);
        $message->shouldReceive('ack')->once()
            ->andThrow(new RuntimeException('Falha de confirmação AMQP'));
        $message->shouldNotReceive('nack');

        try {
            $this->commandUnderTest()->accept($message);
            $this->fail('A falha no ACK deveria ser propagada.');
        } catch (RuntimeException $e) {
            $this->assertSame('Falha de confirmação AMQP', $e->getMessage());
        }

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'sent',
        ]);
    }

    public function test_json_invalido_recebe_nack_sem_requeue(): void
    {
        Mail::fake();
        $message = $this->message('{"json":', false);

        $this->commandUnderTest()->accept($message);

        Mail::assertNothingSent();
    }

    public function test_dados_invalidos_recebem_nack_sem_requeue(): void
    {
        Mail::fake();
        $data = $this->payload();
        $data['agent_email'] = 'email-invalido';
        $message = $this->message(json_encode($data, JSON_THROW_ON_ERROR), false);

        $this->commandUnderTest()->accept($message);

        Mail::assertNothingSent();
    }

    public function test_smtp_incerto_com_unknown_persistido_recebe_ack(): void
    {
        $this->simulateSmtpTimeout();
        $message = $this->message(json_encode($this->payload(), JSON_THROW_ON_ERROR), true);

        $this->commandUnderTest()->accept($message);

        $this->assertDatabaseHas('import_registration_email_processes', [
            'registration_id' => 200,
            'opportunity_id' => 100,
            'status' => 'unknown',
        ]);
    }

    public function test_falha_ao_gravar_unknown_recebe_nack_e_nao_ack(): void
    {
        $this->simulateSmtpTimeout();

        // Simula uma falha de persistência apenas na transição para unknown.
        ImportRegistrationEmailProcess::updating(function (ImportRegistrationEmailProcess $process): void {
            if ($process->status === 'unknown') {
                throw new RuntimeException('Erro ao persistir unknown');
            }
        });

        $message = $this->message(json_encode($this->payload(), JSON_THROW_ON_ERROR), false);

        $this->commandUnderTest()->accept($message);

        // A reserva continuou em sending: nunca reenviar automaticamente.
        $this->assertDatabaseHas('import_registration_email_processes', [
            'registration_id' => 200,
            'opportunity_id' => 100,
            'status' => 'sending',
        ]);
    }

    public function test_erro_antes_smtp_publica_primeiro_retry_e_so_depois_da_ack(): void
    {
        config()->set('rabbitmq.retry.enabled', true);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = $this->message($body, true);
        $processor = $this->retryableProcessor();
        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldReceive('publish')->once()->with($body, 1);

        $this->commandUnderTest($processor, $publisher)->accept($message);
    }

    public function test_falha_na_preparacao_e_retry_posterior_enviam_email_uma_unica_vez(): void
    {
        config()->set('rabbitmq.retry.enabled', true);
        Mail::fake();

        // O problema foi temporário: falha na primeira renderização e passa na segunda.
        $falharUmaVez = true;
        View::composer('emails.import-registration', function () use (&$falharUmaVez): void {
            if ($falharUmaVez) {
                $falharUmaVez = false;
                throw new RuntimeException('Falha transitória no template');
            }
        });

        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldReceive('publish')->once()->with($body, 1);
        $command = $this->commandUnderTest(null, $publisher);

        $command->accept($this->message($body, true));
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'failed',
            'attempts' => 1,
        ]);

        // Simula entrega depois do TTL da fila de retry 1.
        $command->accept($this->message($body, true, 1));

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'sent',
            'attempts' => 2,
        ]);
    }

    public function test_segundo_retry_avanca_para_terceira_fila(): void
    {
        config()->set('rabbitmq.retry.enabled', true);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = $this->message($body, true, 2);
        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldReceive('publish')->once()->with($body, 3);

        $this->commandUnderTest($this->retryableProcessor(), $publisher)->accept($message);
    }

    public function test_depois_da_terceira_tentativa_de_retry_vai_para_dlq(): void
    {
        config()->set('rabbitmq.retry.enabled', true);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = $this->message($body, false, 3);
        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldNotReceive('publish');

        $this->commandUnderTest($this->retryableProcessor(), $publisher)->accept($message);
    }

    public function test_retry_desativado_preserva_comportamento_anterior_dlq(): void
    {
        config()->set('rabbitmq.retry.enabled', false);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = $this->message($body, false);
        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldNotReceive('publish');

        $this->commandUnderTest($this->retryableProcessor(), $publisher)->accept($message);
    }

    public function test_falha_na_publicacao_nao_confirma_nem_rejeita_a_mensagem_original(): void
    {
        config()->set('rabbitmq.retry.enabled', true);
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('has')->once()->with('application_headers')->andReturn(false);
        $message->shouldNotReceive('ack');
        $message->shouldNotReceive('nack');

        $publisher = Mockery::mock(ImportRegistrationRetryPublisher::class);
        $publisher->shouldReceive('publish')->once()->with($body, 1)
            ->andThrow(new RuntimeException('Broker indisponível para retry'));

        try {
            $this->commandUnderTest($this->retryableProcessor(), $publisher)->accept($message);
            $this->fail('A falha de publicação deveria ser propagada.');
        } catch (RuntimeException $e) {
            $this->assertSame('Broker indisponível para retry', $e->getMessage());
        }
    }

    public function test_cabecalho_retry_malformado_vai_direto_para_dlq(): void
    {
        $body = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('has')->once()->with('application_headers')->andReturn(true);
        $message->shouldReceive('get')->once()->with('application_headers')
            ->andReturn(new AMQPTable(['x-import-registration-retry-count' => 'invalido']));
        $message->shouldReceive('nack')->once()->with(false, false);
        $message->shouldNotReceive('ack');

        $processor = Mockery::mock(ImportRegistrationProcessor::class);
        $processor->shouldNotReceive('process');
        $this->commandUnderTest($processor)->accept($message);
    }

    private function retryableProcessor(): ImportRegistrationProcessor
    {
        $processor = Mockery::mock(ImportRegistrationProcessor::class);
        $processor->shouldReceive('process')->once()
            ->andThrow(new RetryableImportRegistrationException('Falha segura antes do SMTP'));
        return $processor;
    }

    /**
     * Mock do AMQPMessage: valida apenas a resposta ACK/NACK, sem conectar ao broker.
     */
    private function message(string $body, bool $shouldAck, int $retryCount = 0): AMQPMessage
    {
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('has')->once()->with('application_headers')
            ->andReturn($retryCount > 0);
        if ($retryCount > 0) {
            $message->shouldReceive('get')->once()->with('application_headers')
                ->andReturn(new AMQPTable(['x-import-registration-retry-count' => $retryCount]));
        }

        if ($shouldAck) {
            $message->shouldReceive('ack')->once();
            $message->shouldNotReceive('nack');
        } else {
            $message->shouldReceive('nack')->once()->with(false, false);
            $message->shouldNotReceive('ack');
        }

        return $message;
    }

    private function commandUnderTest(
        ?ImportRegistrationProcessor $processor = null,
        ?ImportRegistrationRetryPublisher $publisher = null
    ): ImportRegistrationCommand {
        // O RabbitMQService possui conexão no construtor; o mock evita I/O.
        $rabbit = Mockery::mock(RabbitMQService::class);
        $processor = $processor ?? app(ImportRegistrationProcessor::class);
        $publisher = $publisher ?? Mockery::mock(ImportRegistrationRetryPublisher::class);

        return new class($rabbit, $processor, $publisher) extends ImportRegistrationCommand {
            public function accept(AMQPMessage $message): void
            {
                $this->processMessage($message);
            }
        };
    }

    private function simulateSmtpTimeout(): void
    {
        // Renderizacao do mailable precisa do mailer real; somente o envio e simulado.
        $realMailer = app('mailer');
        Mail::shouldReceive('mailer')->andReturn($realMailer);

        Mail::shouldReceive('to')->once()->with('agente@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(ImporteRegistrationMail::class))
            ->andThrow(new RuntimeException('Timeout SMTP'));
    }
}
