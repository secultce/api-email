<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use App\Events\OpinionManagementEvent;

class OpinionManagementCommand extends Command
{
    protected $signature = 'rabbitmq:opinion-management';
    protected $description = 'Comando para o consumer OpinionManagement';
    protected $queueService;

    public function __construct(RabbitMQService $queueService)
    {
        parent::__construct();
        $this->queueService = $queueService;
    }
    public function handle()
    {
        try {
            $queue = config('rabbitmq.queues.queue_opinion_management', 'opinionsPublished');
            $exchange = config('rabbitmq.exchange_default');
            $routingKey = '';
            $this->info("Iniciando consumidor para a fila: {$queue}");
            $this->queueService->consume($queue, $exchange, $routingKey, function (AMQPMessage $msg) {
                $this->processMessage($msg);
            });
        } catch (\Exception $e) {
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
        try {
            $registrations = json_decode($msg->getBody(), true);

            if (!is_array($registrations) || !isset($registrations['registrations'], $registrations['opportunity'])) {
                $this->error('Formato de mensagem inválido');
                Log::error('Formato de mensagem inválido');
                return;
            }


             event(new OpinionManagementEvent($registrations));

            // Confirmar a mensagem após processamento
            Log::info('Confirmando mensagem no RabbitMQ');
            $msg->ack();
            Log::info('Mensagem confirmada com sucesso');
        } catch (\Exception $e) {
            \Sentry\captureMessage($e, \Sentry\Severity::info());
            Log::error('Erro ao processar a mensagem');
            $this->error('Erro ao processar a mensagem: '.$e->getMessage());
        }
    }

/*
 * Formatando o array para incluir a oportunidade em cada item do array
 **/


}
