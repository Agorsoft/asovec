<?php

namespace App\Livewire;

use App\Jobs\RunAppUpdate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class AppUpdater extends Component
{
    use WithFileUploads;

    public ?string $url = null;

    public $package;

    public string $status = 'idle';

    public array $logs = [];

    public ?string $runId = null;

    public ?string $checksum = null;

    protected $rules = [
        'package' => 'file|max:10240', // 10MB default limit
        'url' => 'nullable|url',
    ];

    public function render()
    {
        return view('livewire.app-updater');
    }

    public function mount()
    {
        abort_unless(Auth::user()?->is_admin ?? false, 403);
    }

    public function updatedPackage()
    {
        $this->validateOnly('package');
    }

    public function startFromUpload()
    {
        $this->validate(['package' => 'required|file|max:10240']);

        $uploadsDir = storage_path('app/updates/uploads');
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $filename = Str::random(12).'_'.$this->package->getClientOriginalName();
        $relativePath = 'updates/uploads/'.$filename;

        // Try storeAs first
        // Store explicitly on the local disk (storage/app)
        $path = $this->package->storeAs('updates/uploads', $filename, 'local');

        // Ensure file exists; if not, attempt move as fallback and log diagnostics
        $fullPath = storage_path('app/'.$relativePath);
        if (! file_exists($fullPath)) {
            try {
                $this->package->move($uploadsDir, $filename);
            } catch (\Throwable $e) {
                // Attempt to gather diagnostic info
                $tmpPath = method_exists($this->package, 'getRealPath') ? $this->package->getRealPath() : null;
                $tmpPerm = ($tmpPath && file_exists($tmpPath)) ? substr(sprintf('%o', fileperms($tmpPath)), -4) : 'n/a';
                $this->addLog('Error al mover archivo subido: '.$e->getMessage()."; tmp_path={$tmpPath}; tmp_perms={$tmpPerm}; dest={$fullPath}");

                // Fallback: try copy + unlink in case move/rename fails (different filesystems)
                if ($tmpPath && file_exists($tmpPath)) {
                    try {
                        if (@copy($tmpPath, $fullPath)) {
                            @chmod($fullPath, 0644);
                            @unlink($tmpPath);
                            $this->addLog('Archivo copiado desde tmp a destino como fallback.');
                        } else {
                            $this->addLog('Fallback copy failed from '.$tmpPath.' to '.$fullPath);
                            $this->status = 'failed';

                            return;
                        }
                    } catch (\Throwable $e2) {
                        $this->addLog('Fallback copy exception: '.$e2->getMessage());
                        $this->status = 'failed';

                        return;
                    }
                } else {
                    $this->status = 'failed';

                    return;
                }
            }
        }

        $this->runId = date('YmdHis').'_'.bin2hex(random_bytes(4));

        $this->status = 'queued';
        RunAppUpdate::dispatch($fullPath, $this->runId, $this->checksum);

        $this->addLog("Paquete subido: {$relativePath}. Trabajo encolado. runId: {$this->runId}");
        $this->status = 'running';
    }

    public function startFromUrl()
    {
        $this->validate(['url' => 'required|url']);
        $this->runId = date('YmdHis').'_'.bin2hex(random_bytes(4));

        $this->status = 'queued';
        RunAppUpdate::dispatch($this->url, $this->runId, $this->checksum);
        $this->addLog("Actualización solicitada desde URL: {$this->url}. runId: {$this->runId}");
        $this->status = 'running';
    }

    public function pollLogs()
    {
        if (! $this->runId) {
            return;
        }
        $logPath = storage_path('app/updates/runs/run_'.$this->runId.'/log.txt');
        if (file_exists($logPath)) {
            $content = file_get_contents($logPath);
            $this->logs = explode("\n", trim($content));

            $lc = strtolower($content);

            // Mark completed when we see the success message
            if (str_contains($lc, 'actualización aplicada con éxito') || str_contains($lc, 'installation complete') || str_contains($lc, 'installation complete!')) {
                $this->status = 'completed';

                return;
            }

            // Mark failed on clear failure markers
            if (str_contains($lc, 'error durante la actualización') || str_contains($lc, 'comando falló') || str_contains($lc, 'falló con código')) {
                $this->status = 'failed';

                return;
            }
        }
    }

    protected function addLog(string $line)
    {
        $this->logs[] = '['.now()->toDateTimeString().'] '.$line;
    }
}
