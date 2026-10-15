<?php

namespace App\Console\Commands;

use App\Events\ImportRegistrationEvent;
use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class ImportRegistrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:import-registration-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para consumir a fila de importação de inscrições';

    protected $queueService;

    public function __construct(RabbitMQService $queueService)
    {
        parent::__construct();

        $this->queueService = $queueService;
    }

    /**
     * Executa o comando no console.
     */
    public function handle()
    {
        $queue = config('rabbitmq.queues.queue_import_registration');
        $exchange = config('rabbitmq.exchange_default');
        $routingKey = config('rabbitmq.routing.module_import_registration_draft');

        $this->queueService->consume(
            $queue,
            $exchange,
            $routingKey,
            function (AMQPMessage $msg) {
                $this->processMessage($msg);
            }
        );
    }

    protected function processMessage(AMQPMessage $msg)
    {
        Log::info('Mensagem recebida: ' . $msg->getBody());

        try {
            $registration = json_decode($msg->getBody(), true);

            // Verifica se a mensagem contém uma única inscrição.
            if (
                !is_array($registration) ||
                !array_key_exists('registration', $registration)
            ) {
                Log::error(
                    'Formato de mensagem inválido. Esperada uma única inscrição.',
                    [
                        'body' => $msg->getBody(),
                    ]
                );

                // Descarta a mensagem inválida.
                $msg->ack();

                return;
            }

            event(new ImportRegistrationEvent($registration));

            // Confirma a mensagem após o processamento.
            $msg->ack();

            Log::info(
                'Mensagem processada com sucesso para a inscrição: '
                . $registration['registration']
            );
        } catch (\Throwable $e) {
            \Sentry\captureException($e);

            Log::error('Erro ao processar a mensagem de importação de inscrição.', [
                'exception' => $e->getMessage(),
                'body' => $msg->getBody(),
            ]);
        }
    }
}
