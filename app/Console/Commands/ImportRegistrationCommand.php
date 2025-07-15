<?php

namespace App\Console\Commands;

use App\Mail\PublishedRecourse;
use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;
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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queueService = new RabbitMQService();
        $queue = config('rabbitmq.queue', 'registration_import');
        $exchange = config('rabbitmq.exchange', 'registration');
        $routingKey = config('rabbitmq.routing_key', 'import_registration');
        $queueService->consume($queue, $exchange, $routingKey, function (AMQPMessage $msg) {
            $this->info('Mensagem recebida: ' . $msg->body);

            try {
                $registrations = json_decode($msg->body, true);
                if (!is_array($registrations)) {
                    $this->error('Formato de mensagem inválido');
                    return;
                }

                foreach ($registrations as $registration) {
                    // Lógica para processar cada registro
                    $this->info(sprintf(
                        'Processando inscrição %s da oportunidade %s (%s)',
                        $registration['number'],
                        $registration['opp_name'],
                        $registration['opp_id']
                    ));
                    Mail::to($registration['agent_email'])->send(new ImporteRegistrationMail($registration));
                    // Exemplo: Salvar no banco
                    // \App\Models\Registration::create([
                    //     'registration_id' => $registration['registration'],
                    //     'opportunity_id' => $registration['opp_id'],
                    //     'opportunity_name' => $registration['opp_name'],
                    //     'number' => $registration['number'],
                    //     'owner' => $registration['owner']
                    // ]);
                }
            } catch (\Exception $e) {
                $this->error('Erro ao processar mensagem: ' . $e->getMessage());
            }
        });
    }
}
