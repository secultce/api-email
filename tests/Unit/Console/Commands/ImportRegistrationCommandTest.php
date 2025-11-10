<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ImportRegistrationCommand;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ImporteRegistrationMail;
use PhpAmqpLib\Message\AMQPMessage;
use Mockery;
use Sentry\State\HubInterface;
use Tests\TestCase;

class ImportRegistrationCommandTest extends TestCase
{
    // Configura o ambiente antes de cada teste, usando Mail::fake() e mocks padrão para Log
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    // Limpa os mocks do Mockery após cada teste
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Para criar um mock do AMQPMessage
    protected function createMockAMQPMessage(string $body, string $routingKey): AMQPMessage
    {
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('get')->with('routing_key')->andReturn($routingKey);
        return $message;
    }

    public function test_handle_processes_valid_message_and_sends_emails()
    {
        config(['sentry.dsn' => null]);
        // Mockando para evitar conexaoes reais ao Rabbitmq
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        // Dados completos que a classe ImporteRegistrationMail espera
        $messageBody = json_encode([
            [
                'registration' => '12345',
                'opp_id' => '1',
                'opp_name' => 'Oportunidade Teste 1',
                'number' => '001',
                'agent_name' => 'João Silva',
                'agent_email' => 'test1@example.com',
            ],
            [
                'registration' => '67890',
                'opp_id' => '2',
                'opp_name' => 'Oportunidade Teste 2',
                'number' => '002',
                'agent_name' => 'Maria Santos',
                'agent_email' => 'test2@example.com',
            ]
        ]);

        $msgMock = $this->createMockAMQPMessage($messageBody, 'module_import_registration_draft');

        Log::shouldReceive('info')->byDefault();
        Mail::fake();

        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        try {
            $method->invoke($command, $msgMock);
        } catch (\Exception $e) {
            $this->fail('Exception in processMessage: ' . $e->getMessage());
        }
        $sentEmails = Mail::sent(ImporteRegistrationMail::class);

        // Verificar cada email individualmente
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return $mail->content()->with['agent_email'] === 'test1@example.com';
        });
    }


}
