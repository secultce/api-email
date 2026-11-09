<?php

namespace Tests\Feature;

use App\Mail\ImporteRegistrationMail;
use App\Models\EmailDispatch;
use App\Models\ImportRegistrationEmailProcess;
use App\Services\EmailDeliveryOutcomeUnknownException;
use App\Services\ImportRegistrationProcessor;
use App\Services\InvalidRegistrationMessageException;
use App\Services\RetryableImportRegistrationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ImportRegistrationProcessorTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return array_replace([
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
        ], $changes);
    }

    public function test_primeira_mensagem_envia_uma_vez_e_registra_sucesso(): void
    {
        Mail::fake();
        $data = $this->payload();

        app(ImportRegistrationProcessor::class)->process($data);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'sent',
            'attempts' => 1,
            'message_id' => $data['message_id'],
        ]);
        $this->assertSame(1, EmailDispatch::query()->count());
        $this->assertSame('sent', EmailDispatch::query()->firstOrFail()->status);
    }

    public function test_mensagem_legada_e_canonica_da_mesma_inscricao_geram_um_unico_envio(): void
    {
        Mail::fake();
        $canonical = $this->payload();
        $legacy = [
            'registration' => $canonical['registration'],
            'opp_id' => $canonical['opportunity_id'],
            'opp_name' => $canonical['opportunity_name'],
            'number' => $canonical['number'],
            'agent_name' => $canonical['agent_name'],
            'agent_email' => $canonical['agent_email'],
        ];
        $processor = app(ImportRegistrationProcessor::class);

        $processor->process($legacy);
        $processor->process($canonical);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $dispatch = EmailDispatch::query()->sole();
        $this->assertSame($canonical['opportunity_id'], $dispatch->meta['opportunity_id']);
        $this->assertSame($canonical['opportunity_name'], $dispatch->meta['opportunity_name']);
    }

    public function test_mesmo_message_id_nao_dispara_segundo_email(): void
    {
        Mail::fake();
        $data = $this->payload();
        $processor = app(ImportRegistrationProcessor::class);

        $processor->process($data);
        $processor->process($data);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertSame(1, EmailDispatch::query()->count());
        $this->assertDatabaseHas('import_registration_email_processes', [
            'registration_id' => 200,
            'opportunity_id' => 100,
            'attempts' => 1,
        ]);
    }

    public function test_uuids_diferentes_da_mesma_inscricao_nao_duplicam_email(): void
    {
        Mail::fake();
        $processor = app(ImportRegistrationProcessor::class);
        $first = $this->payload();
        $second = $this->payload(['message_id' => (string) Str::uuid()]);

        $processor->process($first);
        $processor->process($second);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertSame(1, EmailDispatch::query()->count());
        $this->assertSame(1, ImportRegistrationEmailProcess::query()->count());
        $this->assertSame($first['message_id'], ImportRegistrationEmailProcess::query()->firstOrFail()->message_id);
    }

    public function test_status_sending_bloqueia_reenvio(): void
    {
        Mail::fake();
        $this->seedProcess('sending');

        app(ImportRegistrationProcessor::class)->process($this->payload());

        Mail::assertNothingSent();
        $this->assertSame(0, EmailDispatch::query()->count());
    }

    public function test_status_unknown_bloqueia_reenvio(): void
    {
        Mail::fake();
        $this->seedProcess('unknown');

        app(ImportRegistrationProcessor::class)->process($this->payload());

        Mail::assertNothingSent();
        $this->assertSame(0, EmailDispatch::query()->count());
    }

    public function test_status_sent_bloqueia_reenvio(): void
    {
        Mail::fake();
        $this->seedProcess('sent');

        app(ImportRegistrationProcessor::class)->process($this->payload());

        Mail::assertNothingSent();
    }

    public function test_status_failed_permite_nova_tentativa_se_envio_anterior_nao_comecou(): void
    {
        Mail::fake();
        $this->seedProcess('failed', 1);

        app(ImportRegistrationProcessor::class)->process($this->payload());

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'sent',
            'attempts' => 2,
        ]);
    }

    public function test_falha_antes_do_smtp_marca_failed_e_nao_envia(): void
    {
        Mail::fake();
        // Dispara erro na renderização do mailable, antes de Mail::send().
        View::composer('emails.import-registration', function (): void {
            throw new RuntimeException('Falha de preparação do template');
        });

        try {
            app(ImportRegistrationProcessor::class)->process($this->payload());
            $this->fail('Era esperada uma falha de renderização.');
        } catch (RetryableImportRegistrationException $e) {
            $this->assertSame('Falha de preparação do template', $e->getPrevious()->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'failed',
            'attempts' => 1,
        ]);
    }

    public function test_falha_do_smtp_marca_unknown_e_nunca_reenvia_automaticamente(): void
    {
        $data = $this->payload();
        $this->simulateSmtpTimeout();

        try {
            app(ImportRegistrationProcessor::class)->process($data);
            $this->fail('Era esperado resultado desconhecido.');
        } catch (EmailDeliveryOutcomeUnknownException $e) {
            $this->assertNotNull($e->getPrevious());
        }

        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'unknown',
            'attempts' => 1,
        ]);
        $this->assertSame('unknown', EmailDispatch::query()->firstOrFail()->status);

        // Não misturar Mail::fake() com Mail::shouldReceive() no mesmo teste.
        // O mock de send() exige exatamente uma chamada (once).
        app(ImportRegistrationProcessor::class)->process($data);
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'unknown',
            'attempts' => 1,
        ]);
        $this->assertSame(1, EmailDispatch::query()->count());
    }

    public function test_falha_ao_salvar_failed_nao_autoriza_retry_automatico(): void
    {
        Mail::fake();
        View::composer('emails.import-registration', function (): void {
            throw new RuntimeException('Erro ao preparar template');
        });

        ImportRegistrationEmailProcess::updating(function (ImportRegistrationEmailProcess $process): void {
            if ($process->status === 'failed') {
                throw new RuntimeException('Banco indisponível para marcar failed');
            }
        });

        try {
            app(ImportRegistrationProcessor::class)->process($this->payload());
            $this->fail('Era esperada falha de persistência.');
        } catch (RuntimeException $e) {
            $this->assertSame('Banco indisponível para marcar failed', $e->getMessage());
            $this->assertNotInstanceOf(RetryableImportRegistrationException::class, $e);
        }

        Mail::assertNothingSent();
        $this->assertDatabaseHas('import_registration_email_processes', [
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => 'sending',
            'attempts' => 1,
        ]);
    }

    public function test_message_id_invalido_e_rejeitado(): void
    {
        Mail::fake();

        try {
            app(ImportRegistrationProcessor::class)->process(
                $this->payload(['message_id' => 'nao-e-uuid'])
            );
            $this->fail('A mensagem deveria ser inválida.');
        } catch (InvalidRegistrationMessageException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        Mail::assertNothingSent();
        $this->assertSame(0, ImportRegistrationEmailProcess::query()->count());
    }

    public function test_formato_legado_sem_message_id_continua_aceito(): void
    {
        Mail::fake();
        $data = $this->payload();
        unset($data['message_id'], $data['event_type'], $data['schema_version']);

        app(ImportRegistrationProcessor::class)->process($data);

        Mail::assertSent(ImporteRegistrationMail::class, 1);
        $this->assertNotNull(ImportRegistrationEmailProcess::query()->firstOrFail()->message_id);
    }

    private function seedProcess(string $status, int $attempts = 1): void
    {
        ImportRegistrationEmailProcess::query()->create([
            'opportunity_id' => 100,
            'registration_id' => 200,
            'status' => $status,
            'attempts' => $attempts,
            'message_id' => (string) Str::uuid(),
            'agent_email' => 'agente@example.com',
            'started_at' => now(),
        ]);
    }

    private function simulateSmtpTimeout(): void
    {
        // Renderizacao do mailable precisa do mailer real; somente o envio e simulado.
        $realMailer = app('mailer');
        Mail::shouldReceive('mailer')->andReturn($realMailer);

        // Evita transporte externo e simula erro após iniciar a tentativa.
        Mail::shouldReceive('to')->once()->with('agente@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(\Mockery::type(ImporteRegistrationMail::class))
            ->andThrow(new RuntimeException('Timeout SMTP'));
    }
}
