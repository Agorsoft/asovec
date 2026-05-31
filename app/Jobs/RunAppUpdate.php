<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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

    public function handle(): void
    {
        try {
            Log::info('RunAppUpdate: iniciando actualización desde: '.$this->source.' runId: '.$this->runId);

            // Incrementar tiempo de ejecución al máximo posible para la actualización
            if (function_exists('set_time_limit')) {
                @set_time_limit(0);
            }

            $params = [
                'source' => $this->source,
                '--run-id' => $this->runId,
            ];

            if ($this->checksum) {
                $params['--checksum'] = $this->checksum;
            }

            // Llamada directa vía Artisan::call (sin proc_open/subprocess)
            // Esto hace compatible el updater con hosting compartido donde proc_open puede estar deshabilitado.
            $exitCode = Artisan::call('app:update', $params);

            $output = trim(Artisan::output());
            Log::info('RunAppUpdate salida: '.substr($output, 0, 10000));

            if ($exitCode !== 0) {
                Log::error('RunAppUpdate falló con código: '.$exitCode);
                throw new \RuntimeException('El proceso de actualización falló con código: '.$exitCode);
            }

            Log::info('RunAppUpdate completado correctamente.');
        } catch (\Throwable $e) {
            Log::error('RunAppUpdate excepción: '.$e->getMessage());
            throw $e;
        }
    }
}
