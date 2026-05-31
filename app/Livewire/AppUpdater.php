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

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $package = null;

    public string $status = 'idle';

    public array $logs = [];

    public ?string $runId = null;

    public ?string $checksum = null;

    // 256 MB: suficiente para un ZIP con vendor/ incluido
    protected $rules = [
        'package' => 'file|max:262144',
        'url' => 'nullable|url',
    ];

    public function render()
    {
        return view('livewire.app-updater');
    }

    public function mount(): void
    {
        abort_unless(Auth::user()?->is_admin ?? false, 403);
    }

    public function updatedPackage(): void
    {
        $this->validateOnly('package');
    }

    public function startFromUpload(): void
    {
        if ($this->package === null) {
            $this->addLog('Error: no se ha seleccionado ningún archivo.');
            $this->status = 'failed';

            return;
        }

        $this->validate(['package' => 'required|file|max:262144']);

        $uploadsDir = storage_path('app/updates/uploads');
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $originalName = $this->package->getClientOriginalName();
        $filename = Str::random(12).'_'.$originalName;
        $relativePath = 'updates/uploads/'.$filename;

        // Intentar storeAs en disco local
        $this->package->storeAs('updates/uploads', $filename, 'local');
        $fullPath = storage_path('app/'.$relativePath);

        // Fallback: move/copy desde tmp si storeAs falló
        if (! file_exists($fullPath)) {
            $tmpPath = method_exists($this->package, 'getRealPath') ? $this->package->getRealPath() : null;

            if ($tmpPath && file_exists($tmpPath)) {
                if (! @copy($tmpPath, $fullPath)) {
                    $this->addLog("Error: no se pudo copiar el archivo subido a {$fullPath}.");
                    $this->status = 'failed';

                    return;
                }
                @unlink($tmpPath);
            } else {
                $this->addLog('Error: el archivo temporal no existe o ya fue eliminado.');
                $this->status = 'failed';

                return;
            }
        }

        $this->runId = date('YmdHis').'_'.bin2hex(random_bytes(4));
        $this->status = 'running';
        $this->addLog("Archivo recibido: {$originalName}. Iniciando actualización... runId: {$this->runId}");

        try {
            RunAppUpdate::dispatch($fullPath, $this->runId, $this->checksum);
        } catch (\Throwable $e) {
            $this->addLog('Error al despachar el job: '.$e->getMessage());
            $this->status = 'failed';
        }
    }

    public function startFromUrl(): void
    {
        $this->validate(['url' => 'required|url']);
        $this->runId = date('YmdHis').'_'.bin2hex(random_bytes(4));
        $this->status = 'running';
        $this->addLog("Actualización solicitada desde URL: {$this->url}. runId: {$this->runId}");

        try {
            RunAppUpdate::dispatch($this->url, $this->runId, $this->checksum);
        } catch (\Throwable $e) {
            $this->addLog('Error al despachar el job: '.$e->getMessage());
            $this->status = 'failed';
        }
    }

    public function pollLogs(): void
    {
        if (! $this->runId) {
            return;
        }
        $logPath = storage_path('app/updates/runs/run_'.$this->runId.'/log.txt');
        if (file_exists($logPath)) {
            $content = file_get_contents($logPath);
            $this->logs = explode("\n", trim($content));

            $lc = strtolower($content);

            if (str_contains($lc, 'actualización aplicada con éxito')) {
                $this->status = 'completed';

                return;
            }

            if (str_contains($lc, 'error durante la actualización') || str_contains($lc, 'comando falló') || str_contains($lc, 'falló con código')) {
                $this->status = 'failed';

                return;
            }
        }
    }

    protected function addLog(string $line): void
    {
        $this->logs[] = '['.now()->toDateTimeString().'] '.$line;
    }
}
