<?php

namespace App\Console\Commands;

use App\Mail\ImporteRegistrationMail;
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
        $this->info('OpinionManagement comando execultado com sucesso!');
    }

    protected function processMessage(AMQPMessage $msg)
    {
        try {
            $registrations = json_decode($msg->getBody(), true);

            if (!is_array($registrations)) {
                Log::error('Formato de mensagem inválido');
                return;
            }

            foreach ($registrations['registrations'] as $registration) {
//                dump($registration);
                foreach ($registration as $regis)
                {
//                    dump($regis);
                    if (is_array($regis)){
//                        dump($regis['agent']['email']);
//                        Log::info('Emails enviados '. $regis['agent']['email']);
                        $this->info('Emails enviados '. $regis['agent']['email']);
                        Mail::to($regis['agent']['email'])->send(new OpinionManagementMail($regis));

                    }
                }
            }


        } catch (\Exception $e) {
//            \Sentry\captureMessage($e, \Sentry\Severity::info());
            Log::error('Erro ao processar a mensagem');
        }
    }
}
