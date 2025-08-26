<?php

namespace Tests\Unit\Mail;

use App\Models\EmailAudits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveDataEmailDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if an email audit record can be created in the database.
     *
     * @return void
     */
    public function test_can_create_email_audit()
    {
        // Simula dados de uma mensagem RabbitMQ
        $messageData = [
            'email' => 'user@example.com',
            'subject' => 'Test Subject',
            'body' => 'Test body content',
            'action' => 'send',
            'entity' => 'user',
            'agent_id' => 123,
        ];

        // Instancia o model e preenche os dados
        $emailAudit = new EmailAudits();
        $emailAudit->fill($messageData);
        $emailAudit->save();

        // Assert que o registro foi salvo no banco de dados
        $this->assertDatabaseHas('email_audits', [
            'email' => 'user@example.com',
            'subject' => 'Test Subject',
            'body' => 'Test body content',
            'action' => 'send',
            'entity' => 'user',
            'agent_id' => 123,
        ]);

        // Assert que os timestamps foram criados automaticamente
        $this->assertNotNull($emailAudit->created_at);
        $this->assertNotNull($emailAudit->updated_at);
    }
}