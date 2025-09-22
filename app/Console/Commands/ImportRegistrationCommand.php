<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use App\Events\MessageReceivedEvent;
use Illuminate\Support\Facades\Mail;
USE App\Mail\ImporteRegistrationMail;

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
        $queue = config('rabbitmq.queues.queue_import_registration');
        $exchange = config('rabbitmq.exchange_default');
        $routingKey = config('rabbitmq.routing.module_import_registration_draft');
        $this->queueService->consume($queue, $exchange, $routingKey, function (AMQPMessage $msg) {
            $this->processMessage($msg);
        });
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

            foreach ($registrations as $registration) {
                Mail::to($registration['agent_email'])->send(new ImporteRegistrationMail($registration));
                Log::info('Email enviado para ' . $registration['agent_email']);

            }
            // Confirmar a mensagem após processamento
            $msg->ack();
        } catch (\Exception $e) {
            \Sentry\captureMessage($e, \Sentry\Severity::info());
            Log::error('Erro ao processar a mensagem');
        }
    }
}
