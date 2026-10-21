<?php

namespace Tests\Integration;

use App\Models\EmailDispatch;
use App\Models\ImportRegistrationEmailProcess;
use App\Services\ImportRegistrationProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Teste opt-in. Requer banco de TESTE MySQL/PostgreSQL e extensão pcntl.
 * Não usa RefreshDatabase (que abriria transação invisível aos processos filhos).
 * Nunca executar contra banco de produção.
 */
class ImportRegistrationConcurrencyTest extends TestCase
{
    private ?int $registrationId = null;
    private ?int $opportunityId = null;

    public function test_dois_processos_em_paralelo_geram_apenas_um_envio(): void
    {
        if (getenv('RUN_IMPORT_REGISTRATION_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Teste de concorrência exige ativação explícita.');
        }
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('Extensão pcntl indisponível.');
        }

        $connection = DB::connection();
        if (!in_array($connection->getDriverName(), ['pgsql', 'mysql'], true)) {
            $this->markTestSkipped('É necessário PostgreSQL ou MySQL.');
        }
        $databaseName = strtolower((string) $connection->getDatabaseName());
        if (strpos($databaseName, 'test') === false) {
            $this->markTestSkipped('Segurança: o nome do banco precisa conter "test".');
        }

        // IDs isolados: não precisam existir nas tabelas do sistema de origem.
        $this->registrationId = random_int(1800000000, 1999999999);
        $this->opportunityId = random_int(1800000000, 1999999999);
        $startSignal = sys_get_temp_dir() . '/import-registration-start-' . bin2hex(random_bytes(8));
        $children = [];
        $statuses = [];

        try {
            for ($i = 0; $i < 2; $i++) {
                $payload = $this->payload();
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException('Falha ao executar fork para teste de concorrência.');
                }

                if ($pid === 0) {
                    // Não compartilhar conexões PDO herdadas do processo pai.
                    DB::purge(DB::getDefaultConnection());
                    Mail::fake();

                    $deadline = microtime(true) + 15;
                    while (!file_exists($startSignal) && microtime(true) < $deadline) {
                        usleep(10000);
                    }
                    if (!file_exists($startSignal)) {
                        fwrite(STDERR, "Timeout aguardando sincronização\n");
                        exit(2);
                    }

                    try {
                        app(ImportRegistrationProcessor::class)->process($payload);
                        exit(0);
                    } catch (\Throwable $e) {
                        fwrite(STDERR, 'Falha no filho: ' . $e->getMessage() . "\n");
                        exit(1);
                    }
                }

                $children[] = $pid;
            }

            // Libera os dois processos aproximadamente ao mesmo tempo.
            touch($startSignal);
            $deadline = microtime(true) + 30;
            while (count($statuses) < count($children) && microtime(true) < $deadline) {
                foreach ($children as $pid) {
                    if (array_key_exists($pid, $statuses)) {
                        continue;
                    }
                    $result = pcntl_waitpid($pid, $status, WNOHANG);
                    if ($result === $pid) {
                        $statuses[$pid] = $status;
                    }
                }
                usleep(20000);
            }

            foreach ($children as $pid) {
                $this->assertArrayHasKey($pid, $statuses, 'Processo filho excedeu o timeout.');
                $this->assertTrue(pcntl_wifexited($statuses[$pid]));
                $this->assertSame(0, pcntl_wexitstatus($statuses[$pid]));
            }

            $process = ImportRegistrationEmailProcess::query()
                ->where('opportunity_id', $this->opportunityId)
                ->where('registration_id', $this->registrationId)
                ->firstOrFail();

            $this->assertSame('sent', $process->status);
            $this->assertSame(1, $process->attempts);
            $this->assertSame(1, ImportRegistrationEmailProcess::query()
                ->where('opportunity_id', $this->opportunityId)
                ->where('registration_id', $this->registrationId)
                ->count());
            $this->assertCount(1, $this->relatedDispatches());
        } finally {
            if (file_exists($startSignal)) {
                unlink($startSignal);
            }
            foreach ($children as $pid) {
                if (!array_key_exists($pid, $statuses)) {
                    if (function_exists('posix_kill')) {
                        posix_kill($pid, SIGKILL);
                    }
                    pcntl_waitpid($pid, $status, WNOHANG);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->registrationId !== null && $this->opportunityId !== null) {
            $ids = $this->relatedDispatches()->pluck('id')->all();
            if ($ids !== []) {
                DB::table('email_dispatches')->whereIn('id', $ids)->delete();
            }
            ImportRegistrationEmailProcess::query()
                ->where('opportunity_id', $this->opportunityId)
                ->where('registration_id', $this->registrationId)
                ->delete();
        }
        parent::tearDown();
    }

    private function relatedDispatches(): \Illuminate\Support\Collection
    {
        // Evita assumir que o campo meta é JSON nativo ou texto no banco.
        return EmailDispatch::query()->get()->filter(function (EmailDispatch $dispatch): bool {
            $meta = $dispatch->meta;
            return is_array($meta)
                && ($meta['opportunity_id'] ?? null) == $this->opportunityId
                && ($meta['registration'] ?? null) == $this->registrationId;
        });
    }

    private function payload(): array
    {
        return [
            'message_id' => (string) Str::uuid(),
            'event_type' => 'registration.import_requested',
            'schema_version' => 1,
            'occurred_at' => now()->toIso8601String(),
            'registration' => $this->registrationId,
            'opportunity_id' => $this->opportunityId,
            'opportunity_name' => 'Oportunidade concorrente de teste',
            'number' => 'ON-CONCORRENCIA',
            'agent_name' => 'Teste de concorrência',
            'agent_email' => 'concorrencia@example.com',
        ];
    }
}
