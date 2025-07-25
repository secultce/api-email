<?php

namespace App\Console\Commands;

use App\Mail\OpinionManagementMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;
use App\Services\RabbitMQService;

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
        $queue = 'opinion-management';
        $exchange = 'opinion-management';
        $routingKey = '';
        $this->queueService->consume($queue, $exchange, $routingKey, function (AMQPMessage $msg) {
            $this->processMessage($msg);
        });
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
            $regis = $this->getRegistration($registrations);
            foreach ($regis as $registration) {
                Mail::to($registration['agent']['email'])->send(new OpinionManagementMail($registration));
            }
            // Confirmar a mensagem após processamento
            $msg->ack();
        } catch (\Exception $e) {
            \Sentry\captureMessage($e, \Sentry\Severity::info());
            Log::error('Erro ao processar a mensagem');
            $this->error('Erro ao processar a mensagem: ');
        }
    }

/*
 * Formatando o array para incluir a oportunidade em cada item do array
 **/
    protected function getRegistration($registrations): array
    {
        $regis = [];
        foreach ($registrations['registrations'] as $registration) {
            if (
                !isset($registration['number'],
                    $registration['url'],
                    $registration['agent']['email'],
                    $registration['agent']['name']))
            {
                $this->error('Chaves obrigatórias ausentes na inscrição: ' . json_encode($registration));
                continue;
            }
            // adicionando nome da oportunidade
            $regis[] = array_merge($registration, [
                'opportunity' => $registrations['opportunity']['name']
            ]);
        }
        return $regis;
    }

}
