<?php

namespace Tests\Feature;

use App\Mail\MailNotification;
use App\Models\EmailDispatch;
use App\Services\InvalidRegistrationMessageException;
use App\Services\MailNotificationProcessor;
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

    public function test_falha_no_envio_marca_failed_e_relanca(): void
    {
        Mail::shouldReceive('to')->once()->with('agente@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Timeout SMTP'));

        try {
            $this->processor()->process($this->payload());
            $this->fail('A falha no envio deveria ser relançada.');
        } catch (RuntimeException $e) {
            $this->assertSame('Timeout SMTP', $e->getMessage());
        }

        $dispatch = EmailDispatch::first();
        $this->assertSame('failed', $dispatch->status);
        $this->assertSame('Timeout SMTP', $dispatch->error_message);
    }
}
