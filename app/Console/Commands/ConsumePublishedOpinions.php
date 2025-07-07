<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\PublishedOpinions;
use App\Services\AmqpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumePublishedOpinions extends Command
{
    protected $signature = 'rabbitmq:consume-published-opinions-emails';

    protected $description = 'Consome fila com usuários que deverão ser notificados que os pareceres foram publicados';

    public function __construct(
        private readonly AmqpService $amqpService,
    ) {
        parent::__construct();
    }

    /**
     * @throws \Exception
     */
    public function handle(): int
    {
        $queue = config('app.rabbitmq.queues.opinions_published');
        $this->info('🎯 Aguardando e-mails para envio...');
        $this->amqpService->consumeQueue($queue, $this->processMessage(...));

        return Command::SUCCESS;
    }

    private function processMessage(AMQPMessage $message): void
    {
        $data = json_decode($message->getBody(), true);

        foreach ($data['registrations'] as $item) {
            $sent = $this->sendMail($item, $data['opportunity']);

            if (! $sent) {
                // @todo: Decidir o que fazer em caso de erro no envio.
            }
        }

        $message->ack();
    }

    private function sendMail(array $registration, array $opportunity): bool
    {
        try {
            $email = $registration['agent']['email'];
            $name = $registration['agent']['name'];

            $sent = Mail::to($email, $name)
                ->send(new PublishedOpinions([
                    'opportunity' => $opportunity,
                    'registration' => $registration,
                ]));

            $this->info("📧 E-mail enviado para: {$name} <{$email}>");
        } catch (\Exception $e) {
            logger($e->getMessage());

            $this->error("❌ Falha ao enviar e-mail para: {$name} <{$email}>");

            return false;
        }

        return (bool) $sent;
    }
}
