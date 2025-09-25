<?php

namespace App\Console\Commands;

use App\Events\ImportRegistrationEvent;
use App\Mail\ImporteRegistrationMail;
use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;


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
    protected $description = 'Comando para consumir a fila des imports de inscrição';

    protected $queueService;

    public function __construct(RabbitMQService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }
    /**
     * Execultando o comando no console
     */
    public function handle()
    {
        try {
            $queue = config('rabbitmq.queues.queue_import_registration');
            $exchange = config('rabbitmq.exchange_default');
            $routingKey = config('rabbitmq.routing.module_import_registration_draft');
            $this->queueService->consume($queue, $exchange, $routingKey, function (AMQPMessage $msg) {
                $this->processMessage($msg);
            });
        }catch (\Exception $e){
            Log::error('Erro ao iniciar consumidor RabbitMQ: ' . $e->getMessage(), [
                'queue' => $queue,
                'exchange' => $exchange,
                'routingKey' => $routingKey
            ]);
            $this->error('Erro ao iniciar consumidor: ' . $e->getMessage());
        }
    }

    protected function processMessage(AMQPMessage $msg)
    {
        Log::info('Mensagem recebida: ' . $msg->getBody());

        try {
            $registrations = json_decode($msg->getBody(), true);
            if (!is_array($registrations)) {
                Log::error('Formato de mensagem inválido');
                return;
            }
            event(new ImportRegistrationEvent($registrations));
            // Confirmar a mensagem após processamento
            $msg->ack();
            Log::info('Mensagem confirmada com sucesso');
            $this->info('Mensagem confirmada com sucesso.');
        } catch (\Exception $e) {
            \Sentry\captureMessage($e, \Sentry\Severity::info());
            Log::error('Erro ao processar a mensagem');
            $this->error('Erro ao processar a mensagem: '.$e->getMessage());
        }
    }
}
