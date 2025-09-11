<?php

namespace Tests\Unit\Mail;

use App\Models\EmailAudits;
use App\Models\EmailDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveDataEmailDatabaseTest extends TestCase
{
//    use RefreshDatabase;

    /**
     * Test if an email audit record can be created in the database.
     *
     * @return void
     */
    public function test_can_create_email_audit()
    {
        // Simula dados de uma mensagem RabbitMQ
        $this->withoutExceptionHandling();

        $data = [
            'to' => 'user@example.com',
            'subject' => 'Original Subject',
            'content' => 'Test body',
            'mailable_type' => 'App\\Mail\\WelcomeEmail',
            'meta' => ['campaign_id' => 456],
            'dispatched_at' => now()->toDateTimeString(),
        ];

        $dispatch = EmailDispatch::create($data);
        $dispatch->update(['subject' => 'Updated Subject']);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => 'App\\Models\\EmailDispatch',
            'auditable_id' => $dispatch->id,
            'event' => 'updated',
        ]);
    }
}
