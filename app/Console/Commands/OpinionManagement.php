<?php

namespace App\Console\Commands;

use App\Services\RabbitMQService;
use Illuminate\Console\Command;
use PhpAmqpLib\Message\AMQPMessage;

class OpinionManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:opinion-management';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consome mensagens da fila opinionsPublished no RabbitMQ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queueService = new RabbitMQService();
        $queueService->consume('opinionsPublished', 'msg', null, function (AMQPMessage $msg) {
            $this->info('Mensagem recebida: ' . $msg->body);

            try {
                $data = json_decode($msg->body, true);
                if (!is_array($data) || !isset($data['registrations'], $data['opportunity'])) {
                    $this->error('Formato de mensagem inválido');
                    return;
                }

                foreach ($data['registrations'] as $registration) {
                    $this->info(sprintf(
                        'Processando inscrição %s da oportunidade %s',
                        $registration['number'],
                        $data['opportunity']['name']
                    ));

                    // Exemplo: Salvar no banco ou enviar e-mail
                    // \App\Models\Registration::create([
                    //     'number' => $registration['number'],
                    //     'url' => $registration['url'],
                    //     'agent_name' => $registration['agent']['name'],
                    //     'agent_email' => $registration['agent']['email'],
                    //     'opportunity_name' => $data['opportunity']['name'],
                    //     'opportunity_url' => $data['opportunity']['url']
                    // ]);
                }
            } catch (\Exception $e) {
                $this->error('Erro ao processar mensagem: ' . $e->getMessage());
            }
        });
    }
}
