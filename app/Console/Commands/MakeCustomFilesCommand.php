<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;

use Illuminate\Support\Str;


class MakeCustomFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:custom-files {name : O nome do arquivo}';
    protected $description = 'Cria uma visualização de comando, e-mail e e-mail com o nome arquivo';

    public function handle()
    {
        $name = $this->argument('name');
        $this->createCommandFile($name);
        $this->createMailFile($name);
        $this->createViewFile($name);

        $this->info('Arquivo criado com sucesso!');
    }

    protected function createCommandFile($name)
    {
        $path = app_path("Console/Commands/{$name}Command.php");
        $kebabName = Str::kebab($name);
        $stub = <<<EOD
<?php

namespace App\\Console\\Commands;

use Illuminate\\Console\\Command;
use PhpAmqpLib\\Message\\AMQPMessage;
use Illuminate\\Support\\Facades\\Log;
use App\\Services\\RabbitMQService;

class {$name}Command extends Command
{
    protected \$signature = 'rabbitmq:{$kebabName}';
    protected \$description = 'Comando para o consumer {$name}';
    protected \$queueService;

    public function __construct(RabbitMQService \$queueService)
    {
        parent::__construct();
        \$this->queueService = \$queueService;
    }
    public function handle()
    {
        \$queue = '{$kebabName}';
        \$exchange = '{$kebabName}';
        \$routingKey = '{$kebabName}';
        \$this->queueService->consume(\$queue, \$exchange, \$routingKey, function (AMQPMessage \$msg) {
            \$this->processMessage(\$msg);
        });
        \$this->info('{$name} comando execultado com sucesso!');
    }

    protected function processMessage(AMQPMessage \$msg)
    {
        try {
            Log::info('Mensagem recebida: ' . \$msg->getBody());

        } catch (\\Exception \$e) {
            Log::error('Erro ao processar mensagem: ' . \$e->getMessage());

        }
    }
}
EOD;

        file_put_contents($path, $stub);
        $this->line("Comando Criado: {$path}");
    }

    protected function createMailFile($name)
    {
        $path = app_path("Mail/{$name}Mail.php");
        $stub = <<<EOD
<?php

namespace App\\Mail;

use Illuminate\\Bus\\Queueable;
use Illuminate\\Mail\\Mailable;
use Illuminate\\Queue\\SerializesModels;

class {$name}Mail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function build()
    {
        return \$this->view('emails.' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', '{$name}')));
    }
}
EOD;

        file_put_contents($path, $stub);
        $this->line("Criado Email: {$path}");
    }

    protected function createViewFile($name)
    {
        $directory = resource_path('views/emails');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $viewName = Str::kebab($name);
        $path = resource_path("views/emails/{$viewName}.blade.php");
        $stub = <<<EOD
            <!DOCTYPE html>
            <html>
            <head>
                <title>{$name} Email</title>
            </head>
            <body>
                <h1>Bem vindo {$name} Email</h1>
                <p>Esse é um template criado pelo comando personalizado.</p>
            </body>
            </html>
            EOD;

        file_put_contents($path, $stub);
        $this->line("Criado a view: {$path}");
    }
}
