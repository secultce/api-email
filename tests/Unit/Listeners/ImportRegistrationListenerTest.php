<?php

namespace Tests\Unit\Listeners;

use App\Events\ImportRegistrationEvent;
use App\Listeners\ImportRegistrationListener;
use App\Mail\ImporteRegistrationMail;
use App\Models\EmailDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ImportRegistrationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    /**
     * Teste: Envia email com sucesso para uma inscrição válida
     */
    public function test_sends_email_successfully_for_valid_registration()
    {
        // Arrange
        $registrationData = [
            'registration' => 154069287,
            'opp_id' => 7301,
            'opp_name' => 'Teste de importe',
            'number' => 'on-154026963',
            'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'agent_email' => 'liliuchoa616@mail.com',
        ];

        $event = new ImportRegistrationEvent([$registrationData]);
        $listener = new ImportRegistrationListener();

        Log::shouldReceive('info')
            ->once()
            ->with('Enviando e-mail para: liliuchoa616@mail.com');

        Log::shouldReceive('info')
            ->once()
            ->with('Email enviado e auditado para: liliuchoa616@mail.com');

        // Act
        $listener->handle($event);

        // Assert: Verifica se o email foi enviado
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) use ($registrationData) {
            return $mail->hasTo('liliuchoa616@mail.com') &&
                   $mail->content()->with['registration'] === 154069287 &&
                   $mail->content()->with['opp_name'] === 'Teste de importe' &&
                   $mail->content()->with['agent_name'] === 'MARIA LILIANA UCHOA DO NASCIMENTO';
        });

        // Assert: Verifica se foi criado o registro de auditoria
        $this->assertDatabaseHas('email_dispatches', [
            'to' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'mailable_type' => ImporteRegistrationMail::class,
        ]);
    }

    /**
     * Teste: Envia múltiplos emails para múltiplas inscrições
     */
    public function test_sends_multiple_emails_for_multiple_registrations()
    {
        // Arrange
        $registrations = [
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@mail.com',
            ],
            [
                'registration' => 154069288,
                'opp_id' => 7302,
                'opp_name' => 'Segunda oportunidade',
                'number' => 'on-154026964',
                'agent_name' => 'JOAO DA SILVA',
                'agent_email' => 'joao.silva@example.com',
            ]
        ];

        $event = new ImportRegistrationEvent($registrations);
        $listener = new ImportRegistrationListener();

        // Act
        $listener->handle($event);

        // Assert: Verifica se ambos os emails foram enviados
        Mail::assertSent(ImporteRegistrationMail::class, 2);

        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->hasTo('liliuchoa616@mail.com');
        });

        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->hasTo('joao.silva@example.com');
        });

        // Assert: Verifica se ambos os registros de auditoria foram criados
        $this->assertDatabaseCount('email_dispatches', 2);
    }

    /**
     * Teste: Cria registro de auditoria no EmailDispatch ao enviar email
     */
    public function test_creates_email_dispatch_audit_record()
    {
        // Arrange
        $registrationData = [
            'registration' => 154069287,
            'opp_id' => 7301,
            'opp_name' => 'Teste de importe',
            'number' => 'on-154026963',
            'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'agent_email' => 'liliuchoa616@mail.com',
        ];

        $event = new ImportRegistrationEvent([$registrationData]);
        $listener = new ImportRegistrationListener();

        // Act
        $listener->handle($event);

        // Assert: Verifica o registro de auditoria
        $this->assertDatabaseHas('email_dispatches', [
            'to' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'subject' => 'Convite para uma nova fase',
            'mailable_type' => ImporteRegistrationMail::class,
        ]);

        // Verifica os metadados salvos
        $emailDispatch = EmailDispatch::first();
        $this->assertEquals(154069287, $emailDispatch->meta['registration']);
        $this->assertEquals(7301, $emailDispatch->meta['opp_id']);
        $this->assertEquals('Teste de importe', $emailDispatch->meta['opp_name']);
        $this->assertEquals('on-154026963', $emailDispatch->meta['number']);
        $this->assertEquals('MARIA LILIANA UCHOA DO NASCIMENTO', $emailDispatch->meta['agent_name']);
        $this->assertEquals('liliuchoa616@mail.com', $emailDispatch->meta['agent_email']);
        $this->assertNotNull($emailDispatch->dispatched_at);
    }

    /**
     * Teste: Não envia email quando faltam campos obrigatórios
     */
    public function test_does_not_send_email_when_required_fields_are_missing()
    {
        // Arrange: Inscrição sem campo 'agent_email'
        $invalidRegistration = [
            'registration' => 154069287,
            'opp_id' => 7301,
            'opp_name' => 'Teste de importe',
            'number' => 'on-154026963',
            'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            // 'agent_email' está faltando
        ];

        $event = new ImportRegistrationEvent([$invalidRegistration]);
        $listener = new ImportRegistrationListener();

        Log::shouldReceive('error')
            ->once()
            ->with('Chaves obrigatórias ausentes na inscrição: ' . json_encode($invalidRegistration));

        // Act
        $listener->handle($event);

        // Assert: Verifica que nenhum email foi enviado
        Mail::assertNotSent(ImporteRegistrationMail::class);

        // Assert: Verifica que nenhum registro de auditoria foi criado
        $this->assertDatabaseCount('email_dispatches', 0);
    }

    /**
     * Teste: Processa apenas inscrições válidas quando há mix de válidas e inválidas
     */
    public function test_processes_only_valid_registrations_in_mixed_batch()
    {
        // Arrange
        $registrations = [
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@mail.com',
            ],
            [
                // Inscrição inválida - faltando 'agent_email'
                'registration' => 154069288,
                'opp_id' => 7302,
                'opp_name' => 'Segunda oportunidade',
                'number' => 'on-154026964',
                'agent_name' => 'JOAO DA SILVA',
            ],
            [
                'registration' => 154069289,
                'opp_id' => 7303,
                'opp_name' => 'Terceira oportunidade',
                'number' => 'on-154026965',
                'agent_name' => 'PEDRO SANTOS',
                'agent_email' => 'pedro.santos@example.com',
            ]
        ];

        $event = new ImportRegistrationEvent($registrations);
        $listener = new ImportRegistrationListener();

        // Act
        $listener->handle($event);

        // Assert: Verifica que apenas 2 emails foram enviados (os válidos)
        Mail::assertSent(ImporteRegistrationMail::class, 2);

        // Assert: Verifica que apenas 2 registros de auditoria foram criados
        $this->assertDatabaseCount('email_dispatches', 2);

        // Assert: Verifica que os emails corretos foram enviados
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->hasTo('liliuchoa616@mail.com');
        });

        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->hasTo('pedro.santos@example.com');
        });
    }

    /**
     * Teste: Valida todos os campos obrigatórios
     */
    public function test_validates_all_required_fields()
    {
        // Arrange: Testa cada campo obrigatório faltando
        $requiredFields = ['registration', 'opp_id', 'opp_name', 'number', 'agent_name', 'agent_email'];

        foreach ($requiredFields as $missingField) {
            Mail::fake(); // Reseta o fake para cada iteração

            $incompleteRegistration = [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@mail.com',
            ];

            // Remove o campo que está sendo testado
            unset($incompleteRegistration[$missingField]);

            $event = new ImportRegistrationEvent([$incompleteRegistration]);
            $listener = new ImportRegistrationListener();

            // Act
            $listener->handle($event);

            // Assert: Verifica que nenhum email foi enviado quando qualquer campo obrigatório está faltando
            Mail::assertNotSent(ImporteRegistrationMail::class,
                "Email não deveria ser enviado quando o campo '{$missingField}' está faltando"
            );
        }
    }

    /**
     * Teste: Verifica o conteúdo do email enviado
     */
    public function test_email_contains_correct_data()
    {
        // Arrange
        $registrationData = [
            'registration' => 154069287,
            'opp_id' => 7301,
            'opp_name' => 'Teste de importe',
            'number' => 'on-154026963',
            'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'agent_email' => 'liliuchoa616@mail.com',
        ];

        $event = new ImportRegistrationEvent([$registrationData]);
        $listener = new ImportRegistrationListener();

        // Act
        $listener->handle($event);

        // Assert: Verifica todos os campos do email
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) use ($registrationData) {
            $content = $mail->content();
            return $content->with['registration'] === 154069287 &&
                   $content->with['opp_id'] === 7301 &&
                   $content->with['opp_name'] === 'Teste de importe' &&
                   $content->with['number'] === 'on-154026963' &&
                   $content->with['agent_name'] === 'MARIA LILIANA UCHOA DO NASCIMENTO' &&
                   $content->with['agent_email'] === 'liliuchoa616@mail.com';
        });

        // Assert: Verifica o envelope (assunto)
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->envelope()->subject === 'Convite para uma nova fase';
        });
    }

    /**
     * Teste: Loga as informações corretas durante o envio
     */
    public function test_logs_correct_information_during_sending()
    {
        // Arrange
        $registrationData = [
            'registration' => 154069287,
            'opp_id' => 7301,
            'opp_name' => 'Teste de importe',
            'number' => 'on-154026963',
            'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
            'agent_email' => 'liliuchoa616@mail.com',
        ];

        $event = new ImportRegistrationEvent([$registrationData]);
        $listener = new ImportRegistrationListener();

        Log::shouldReceive('info')
            ->once()
            ->with('Enviando e-mail para: liliuchoa616@mail.com');

        Log::shouldReceive('info')
            ->once()
            ->with('Email enviado e auditado para: liliuchoa616@mail.com');

        // Act
        $listener->handle($event);

        // Assert implícito via Mockery
        $this->assertTrue(true);
    }
}
