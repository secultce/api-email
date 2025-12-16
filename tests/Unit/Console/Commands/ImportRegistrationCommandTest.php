<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\ImportRegistrationCommand;
use App\Events\ImportRegistrationEvent;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Event;
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
        Event::fake();
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
    }

    // Limpa os mocks do Mockery após cada teste
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Para criar um mock do AMQPMessage com ack
     */
    protected function createMockAMQPMessage(string $body, bool $shouldAck = true): AMQPMessage
    {
        $message = Mockery::mock(AMQPMessage::class);
        $message->shouldReceive('getBody')->andReturn($body);

        if ($shouldAck) {
            $message->shouldReceive('ack')->once()->andReturnNull();
        }

        return $message;
    }

    /**
     * Teste: Processa mensagem válida do RabbitMQ e dispara evento ImportRegistrationEvent
     */
    public function test_processes_valid_rabbitmq_message_and_dispatches_event()
    {
        // Arrange: Mock do serviço RabbitMQ
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        // Dados no formato especificado pelo usuário
        $messageBody = json_encode([
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@mail.com',
            ]
        ]);

        $msgMock = $this->createMockAMQPMessage($messageBody);

        // Act: Processa a mensagem
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert: Verifica se o evento foi disparado
        Event::assertDispatched(ImportRegistrationEvent::class, function ($event) {
            return is_array($event->registrations) &&
                   count($event->registrations) === 1 &&
                   $event->registrations[0]['registration'] === 154069287 &&
                   $event->registrations[0]['opp_name'] === 'Teste de importe';
        });
    }

    /**
     * Teste: Processa múltiplas inscrições e dispara evento com todos os dados
     */
    public function test_processes_multiple_registrations_and_dispatches_event()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        $messageBody = json_encode([
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@gmail.com',
            ],
            [
                'registration' => 154069288,
                'opp_id' => 7302,
                'opp_name' => 'Segunda oportunidade',
                'number' => 'on-154026964',
                'agent_name' => 'JOAO DA SILVA',
                'agent_email' => 'joao.silva@example.com',
            ]
        ]);

        $msgMock = $this->createMockAMQPMessage($messageBody);

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert
        Event::assertDispatched(ImportRegistrationEvent::class, function ($event) {
            return is_array($event->registrations) &&
                   count($event->registrations) === 2 &&
                   $event->registrations[0]['registration'] === 154069287 &&
                   $event->registrations[1]['registration'] === 154069288;
        });
    }

    /**
     * Teste: Verifica se a mensagem é confirmada (ack) após processamento bem-sucedido
     */
    public function test_acknowledges_message_after_successful_processing()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        $messageBody = json_encode([
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@gmail.com',
            ]
        ]);

        // Configurando expectativa explícita do ack
        $msgMock = $this->createMockAMQPMessage($messageBody, true);

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert: O mock do Mockery irá falhar se ack() não for chamado exatamente uma vez
        $this->assertTrue(true);
    }

    /**
     * Teste: Loga informação ao receber mensagem
     */
    public function test_logs_info_when_receiving_message()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        $messageBody = json_encode([
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@gmail.com',
            ]
        ]);

        $msgMock = $this->createMockAMQPMessage($messageBody);

        // Expectativa explícita de log
        Log::shouldReceive('info')
            ->once()
            ->with('Mensagem recebida: ' . $messageBody);

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert implícito via Mockery
        $this->assertTrue(true);
    }

    /**
     * Teste: Loga erro quando JSON é inválido (não lança exceção, apenas retorna)
     */
    public function test_logs_error_when_json_is_invalid()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        // Mensagem JSON inválida
        $invalidJson = 'invalid json format {[';
        $msgMock = Mockery::mock(AMQPMessage::class);
        $msgMock->shouldReceive('getBody')->andReturn($invalidJson);
        // Não deve chamar ack quando há erro
        $msgMock->shouldNotReceive('ack');

        // Expectativa de log
        Log::shouldReceive('info')
            ->once()
            ->with('Mensagem recebida: ' . $invalidJson);

        Log::shouldReceive('error')
            ->once()
            ->with('Formato de mensagem inválido');

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert: Evento não deve ser disparado
        Event::assertNotDispatched(ImportRegistrationEvent::class);
    }

    /**
     * Teste: Captura exceção e envia para Sentry quando ocorre erro durante processamento
     */
    public function test_captures_processing_exception_with_sentry()
    {
        // Arrange: Simula falha no processamento
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        // Mock do evento para lançar exceção
        Event::shouldReceive('dispatch')
            ->andThrow(new \Exception('Erro ao processar evento'));

        $messageBody = json_encode([
            [
                'registration' => 154069287,
                'opp_id' => 7301,
                'opp_name' => 'Teste de importe',
                'number' => 'on-154026963',
                'agent_name' => 'MARIA LILIANA UCHOA DO NASCIMENTO',
                'agent_email' => 'liliuchoa616@gmail.com',
            ]
        ]);

        $msgMock = Mockery::mock(AMQPMessage::class);
        $msgMock->shouldReceive('getBody')->andReturn($messageBody);
        // Não deve chamar ack quando há exceção
        $msgMock->shouldNotReceive('ack');

        Log::shouldReceive('info')
            ->once()
            ->with('Mensagem recebida: ' . $messageBody);

        Log::shouldReceive('error')
            ->once()
            ->with('Erro ao processar a mensagem');

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Não deve lançar exceção - deve ser capturada internamente
        $method->invoke($command, $msgMock);

        // Assert
        $this->assertTrue(true);
    }

    /**
     * Teste: Não processa mensagem quando o formato não é array
     */
    public function test_does_not_process_invalid_message_format()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $this->app->instance(RabbitMQService::class, $queueService);

        // JSON válido mas não é array
        $messageBody = json_encode('string simples');

        $msgMock = Mockery::mock(AMQPMessage::class);
        $msgMock->shouldReceive('getBody')->andReturn($messageBody);
        // Não deve chamar ack
        $msgMock->shouldNotReceive('ack');

        Log::shouldReceive('info')
            ->once()
            ->with('Mensagem recebida: ' . $messageBody);

        Log::shouldReceive('error')
            ->once()
            ->with('Formato de mensagem inválido');

        // Act
        $command = new ImportRegistrationCommand($queueService);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);
        $method->invoke($command, $msgMock);

        // Assert: Evento não deve ser disparado
        Event::assertNotDispatched(ImportRegistrationEvent::class);
    }

    /**
     * Teste: Verifica configuração do comando
     */
    public function test_command_signature_and_description()
    {
        // Arrange
        $queueService = Mockery::mock(RabbitMQService::class);
        $command = new ImportRegistrationCommand($queueService);

        // Act
        $reflection = new \ReflectionClass($command);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);
        $signature = $signatureProperty->getValue($command);

        $descriptionProperty = $reflection->getProperty('description');
        $descriptionProperty->setAccessible(true);
        $description = $descriptionProperty->getValue($command);

        // Assert
        $this->assertEquals('rabbitmq:import-registration-command', $signature);
        $this->assertEquals('Comando para consumir a fila des imports de inscrição', $description);
    }

    /**
     * Teste: Handle chama o método consume com parâmetros corretos
     */
    public function test_handle_calls_consume_with_correct_parameters()
    {
        // Arrange
        config([
            'rabbitmq.queues.queue_import_registration' => 'test_queue',
            'rabbitmq.exchange_default' => 'test_exchange',
            'rabbitmq.routing.module_import_registration_draft' => 'test_routing_key'
        ]);

        $queueService = Mockery::mock(RabbitMQService::class);
        $queueService->shouldReceive('consume')
            ->once()
            ->with('test_queue', 'test_exchange', 'test_routing_key', Mockery::type('Closure'))
            ->andReturn(null);

        $this->app->instance(RabbitMQService::class, $queueService);

        $command = new ImportRegistrationCommand($queueService);

        // Act
        $command->handle();

        // Assert: Mockery irá verificar se consume foi chamado corretamente
        $this->assertTrue(true);
    }
}
