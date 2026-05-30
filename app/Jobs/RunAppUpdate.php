<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class RunAppUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $source;

    public string $runId;

    public ?string $checksum;

    public function __construct(string $source, ?string $runId = null, ?string $checksum = null)
    {
        $this->source = $source;
        $this->runId = $runId ?: date('YmdHis').'_'.bin2hex(random_bytes(4));
        $this->checksum = $checksum;
        $this->onQueue('updates');
    }

    public function handle()
    {
        try {
            Log::info('RunAppUpdate: iniciando actualización desde: '.$this->source.' runId: '.$this->runId);

            $updatesPath = storage_path('app/updates');
            $runDir = $updatesPath.'/runs/run_'.$this->runId;
            if (! is_dir($runDir)) {
                mkdir($runDir, 0755, true);
            }
            $logFile = $runDir.'/log.txt';

            $phpFinder = new PhpExecutableFinder;
            $phpExecutable = $phpFinder->find(false) ?: PHP_BINARY ?: 'php';
            $phpExecutable = escapeshellarg($phpExecutable);

            $cmd = sprintf('%s %s app:update %s --run-id=%s', $phpExecutable, escapeshellarg(base_path('artisan')), escapeshellarg($this->source), escapeshellarg($this->runId));
            if ($this->checksum) {
                $cmd .= ' --checksum='.escapeshellarg($this->checksum);
            }

            $process = Process::fromShellCommandline($cmd, base_path());
            $process->setTimeout(0);
            $process->run(function ($type, $buffer) use ($logFile) {
                file_put_contents($logFile, $buffer, FILE_APPEND | LOCK_EX);
            });

            $output = file_get_contents($logFile) ?: '';
            Log::info('RunAppUpdate salida: '.substr($output, 0, 10000));
            if (! $process->isSuccessful()) {
                Log::error('RunAppUpdate falló con código: '.$process->getExitCode());
                throw new \RuntimeException('Proceso app:update falló con código: '.$process->getExitCode());
            }
            Log::info('RunAppUpdate completado correctamente.');
        } catch (\Throwable $e) {
            Log::error('RunAppUpdate excepción: '.$e->getMessage());
            throw $e;
        }
    }
}
