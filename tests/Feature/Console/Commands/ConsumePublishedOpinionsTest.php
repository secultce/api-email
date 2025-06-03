<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsumePublishedOpinionsTest extends TestCase
{
    public function test_command_starts_and_runs_correctly(): void
    {
        $process = new Process(['php', 'artisan', 'rabbitmq:consume-published-opinions-emails']);
        $process->start();

        $this->assertTrue($process->isRunning());

        $process->signal(SIGINT);

        // Give it a moment to process the signal
        sleep(1);

        $this->assertFalse($process->isRunning());
        $this->assertEquals(130, $process->getExitCode());
    }

    public function test_process_message(): void
    {

    }
}
