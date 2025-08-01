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

        $messageBody = json_encode([
            ['agent_email' => 'test1@example.com'],
            ['agent_email' => 'test2@example.com'],
        ]);
        $msgMock = $this->createMockAMQPMessage($messageBody, 'import_registration');
        Log::shouldReceive('info')
            ->once()
            ->with('Mensagem recebida: ' . $messageBody);

        Mail::fake();
        Log::info( 'Email enviado para test1@example.com');
        // Criando um instancia para o serviço
        $command = new ImportRegistrationCommand($queueService);

        $reflection = new \ReflectionClass($command);
        // método processMessage sem precisar chamar métodos que causem travamento (como o handle ou consume)
        // mas testa a lógica de processamento
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        try {
            $method->invoke($command, $msgMock);
        } catch (\Exception $e) {
            $this->fail('Exception in processMessage: ' . $e->getMessage());
        }

        // Verifique se os e-mails são enviados
        Mail::assertSent(ImporteRegistrationMail::class, function ($mail) {
            return in_array($mail->to[0]['address'], ['test1@example.com', 'test2@example.com']);
        });
        Mail::assertSent(ImporteRegistrationMail::class, 2);
    }


}
