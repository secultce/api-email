<?php

namespace Tests\Feature;

use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use App\Services\EmailDeliveryOutcomeUnknownException;
use App\Services\InvalidRegistrationMessageException;
use App\Services\MailNotificationProcessor;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class MailNotificationProcessorTest extends TestCase
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

    private function processor(): MailNotificationProcessor
    {
        return app(MailNotificationProcessor::class);
    }

    public function test_envia_email_com_html_recebido_e_registra_dispatch(): void
    {
        Mail::fake();

        $this->processor()->process($this->payload());

        Mail::assertSent(MailNotification::class, function (MailNotification $mail) {
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
        $payload = $this->payload();

        $this->processor()->process($payload);
        $this->processor()->process($payload);

        Mail::assertSent(MailNotification::class, 1);
        $this->assertSame(1, EmailDispatch::count());
    }

    public function test_dados_invalidos_lancam_excecao_sem_enviar(): void
    {
        Mail::fake();

        $this->expectException(InvalidRegistrationMessageException::class);

        try {
            $this->processor()->process($this->payload(['to' => 'invalido']));
        } finally {
            Mail::assertNothingSent();
            $this->assertSame(0, EmailDispatch::count());
        }
    }

    public function test_falha_no_envio_marca_unknown_e_nao_reenvia(): void
    {
        $payload = $this->payload();
        Mail::shouldReceive('to')->once()->with('agente@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Timeout SMTP'));

        try {
            $this->processor()->process($payload);
            $this->fail('O resultado incerto deveria ser sinalizado.');
        } catch (EmailDeliveryOutcomeUnknownException $e) {
            $this->assertSame('Timeout SMTP', $e->getPrevious()->getMessage());
        }

        $dispatch = EmailDispatch::sole();
        $this->assertSame('unknown', $dispatch->status);
        $this->assertSame('Timeout SMTP', $dispatch->error_message);

        // Reprocessar a mesma mensagem (ex.: replay da DLQ) não envia de novo
        $this->processor()->process($payload);
        $this->assertSame(1, EmailDispatch::count());
    }

    public function test_falha_ao_registrar_sent_apos_envio_nao_marca_failed(): void
    {
        Mail::fake();
        $payload = $this->payload();

        // Simula o banco falhando logo depois de o SMTP aceitar o e-mail
        EmailDispatch::updating(function (EmailDispatch $dispatch) {
            if ($dispatch->status === 'sent') {
                throw new RuntimeException('Conexão com o banco perdida');
            }
        });

        try {
            $this->processor()->process($payload);
            $this->fail('O resultado incerto deveria ser sinalizado.');
        } catch (EmailDeliveryOutcomeUnknownException) {
        }

        Mail::assertSent(MailNotification::class, 1);
        $this->assertSame('unknown', EmailDispatch::sole()->status);

        $this->processor()->process($payload);
        Mail::assertSent(MailNotification::class, 1);
    }

    public function test_reserva_existente_em_envio_bloqueia_outro_consumidor(): void
    {
        Mail::fake();
        $payload = $this->payload();

        EmailDispatch::create([
            'to' => $payload['to'],
            'subject' => $payload['subject'],
            'mailable_type' => MailNotification::class,
            'meta' => ['message_id' => $payload['message_id']],
            'status' => 'sending',
        ]);

        $this->processor()->process($payload);

        Mail::assertNothingSent();
        $this->assertSame(1, EmailDispatch::count());
    }

    public function test_indice_unico_impede_dois_dispatches_com_mesmo_message_id(): void
    {
        $row = [
            'to' => 'agente@example.com',
            'subject' => 'Inscrição enviada',
            'mailable_type' => MailNotification::class,
            'meta' => json_encode(['message_id' => 'sendmailnotification:dup']),
            'status' => 'sending',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        EmailDispatch::query()->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        EmailDispatch::query()->insert($row);
    }

    public function test_dispatch_failed_pode_ser_reprocessado(): void
    {
        Mail::fake();
        $payload = $this->payload();

        EmailDispatch::create([
            'to' => $payload['to'],
            'subject' => $payload['subject'],
            'mailable_type' => MailNotification::class,
            'meta' => ['message_id' => $payload['message_id']],
            'status' => 'failed',
            'error_message' => 'erro anterior',
        ]);

        $this->processor()->process($payload);

        Mail::assertSent(MailNotification::class, 1);
        $dispatch = EmailDispatch::sole();
        $this->assertSame('sent', $dispatch->status);
        $this->assertNull($dispatch->error_message);
    }
}
