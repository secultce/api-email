<?php

namespace Tests\Unit\Mail;

use DB;
use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Models\EmailDispatch;
use App\Services\RabbitMQService;
use App\Events\MessageReceivedEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailDispatchTest extends TestCase
{
    // use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        config(['auditing.driver' => 'database']);
        config(['audit.enabled' => true]);
    }

    /**
     * Test if an email dispatch record can be created and audited.
     *
     * @return void
     */
    public function test_can_create_email_dispatch_and_audit()
    {
         $user = \App\Models\User::factory()->create();
         $this->actingAs($user);

         // Dados para criar um registro de EmailDispatch
         $emailDispatch = EmailDispatch::create([
            'to' => 'user@example.com',
            'subject' => 'Original Subject',
            'content' => 'Test body',
            'mailable_type' => 'App\\Mail\\TestMail',
            'meta' => ['campaign_id' => 456],
            'dispatched_at' => now()
        ]);
        // Mostrando no terminal os dados das tabelas para debug
        \DB::table('email_dispatches')->get()->dump();
        \DB::table('audits')->get()->dump();

        // Verifica se o registro foi salvo no banco de dados
        $this->assertDatabaseHas('email_dispatches', [
            'to' => 'user@example.com',
            'subject' => 'Original Subject',
            'content' => 'Test body',
            'mailable_type' => 'App\\Mail\\TestMail',
            'id' => $emailDispatch->id,
        ]);

        // Verifica se a auditoria foi criada na tabela 'audits'
        $this->assertDatabaseHas('audits', [
            'auditable_type' => EmailDispatch::class,
            'auditable_id' => $emailDispatch->id,
            'event' => 'created',
        ]);

        // Verifica se os timestamps foram criados automaticamente
        $this->assertNotNull($emailDispatch->created_at);
        $this->assertNotNull($emailDispatch->updated_at);
    }

    /**
     * Test if an email dispatch record can be updated and audited.
     *
     * @return void
     */
    public function test_can_update_email_dispatch_and_audit()
    {
        $user = \App\Models\User::factory()->create();
         $this->actingAs($user);

        // Cria um registro de EmailDispatch
        $emailDispatch = EmailDispatch::create([
            'to' => 'user@example.com',
            'subject' => 'Original Subject',
            'content' => 'Test body',
            'mailable_type' => 'App\\Mail\\TestMail',
            'meta' => ['campaign_id' => 456],
            'dispatched_at' => now()
        ]);

        // Atualiza o registro
        $emailDispatch->update([
            'subject' => 'Updated Subject',
        ]);

        // Verifica se o evento de auditoria foi registrado
        $this->assertDatabaseHas('audits', [
            'auditable_type' => 'App\Models\EmailDispatch',
            'auditable_id' => $emailDispatch->id,
            'event' => 'updated',
        ]);

        \DB::table('audits')->get()->dump();

        // Verifica se a auditoria foi criada na tabela 'audits'
        $this->assertDatabaseHas('audits', [
            'auditable_type' => EmailDispatch::class,
            'auditable_id' => $emailDispatch->id,
            'event' => 'created',
        ]);
    }

    public function test_can_audit_rabbitmq_message()
    {
         $user = User::factory()->create();
         $this->actingAs($user);


        $mock = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $mock);

        $messageData = [
            'to' => 'test@example.com',
            'subject' => 'Test Subject',
            'content' => 'Test Content',
        ];

        $mock->shouldReceive('consume')
            ->once()
            ->with('email_queue', Mockery::on(function ($callback) use ($messageData) {
                $mockMessage = Mockery::mock();
                $mockMessage->body = json_encode($messageData);
                $mockMessage->shouldReceive('ack')->once();
                $callback($mockMessage);
                return true;
            }));

        Event::fake();

        // $this->artisan('rabbitmq:import-registration-command');
        
        Event::assertDispatched(MessageReceivedEvent::class, function ($event) use ($messageData) {
            return $event->data === $messageData && $event->queue === 'email_queue';
        });

        $this->assertDatabaseHas('message_audits', [
            'queue' => 'email_queue',
            'payload' => json_encode($messageData),
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('audits', [
            'auditable_type' => 'App\\Models\\MessageAudit',
            'event' => 'created',
            'user_id' => $user->id,
        ]);
    }
}