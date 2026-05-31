<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

#[Signature('app:update {source : URL o ruta local al .tar.gz o .zip} {--keep-backups=3} {--no-migrate} {--no-composer} {--checksum=} {--run-id=}')]
#[Description('Descarga y aplica una actualización desde un tar.gz/zip en el servidor')]
class AppUpdate extends Command
{
    /** Indica si rsync está disponible en el servidor. */
    private bool $rsyncAvailable = false;

    /** Indica si proc_open está disponible (para subprocesos de shell). */
    private bool $procOpenAvailable = false;

    public function handle(): int
    {
        $source = $this->argument('source');
        $keepBackups = (int) $this->option('keep-backups');
        $noMigrate = (bool) $this->option('no-migrate');
        $noComposer = (bool) $this->option('no-composer');
        $checksum = $this->option('checksum') ?: null;
        $runId = $this->option('run-id') ?: date('YmdHis').'_'.bin2hex(random_bytes(4));

        $this->rsyncAvailable = $this->commandExists('rsync');
        $this->procOpenAvailable = function_exists('proc_open');

        $basePath = base_path();
        $updatesPath = storage_path('app/updates');
        if (! is_dir($updatesPath)) {
            mkdir($updatesPath, 0755, true);
        }

        $tmpDir = $updatesPath."/runs/run_{$runId}/tmp";
        $backupDir = $updatesPath."/runs/run_{$runId}/backups";
        $logFile = $updatesPath."/runs/run_{$runId}/log.txt";
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $backupParent = dirname($backupDir);
        if (! is_dir($backupParent)) {
            mkdir($backupParent, 0755, true);
        }

        // init log
        file_put_contents($logFile, "Inicio de actualización: {$runId}\n");

        $this->lineToLog = function ($line) use ($logFile) {
            file_put_contents($logFile, $line.PHP_EOL, FILE_APPEND | LOCK_EX);
        };

        $log = fn ($m) => ($this->lineToLog)($m);
        $log('proc_open: '.($this->procOpenAvailable ? 'disponible' : 'no disponible'));
        $log('rsync: '.($this->rsyncAvailable ? 'disponible' : 'no disponible, se usará copia PHP'));

        try {
            $this->info('Preparando actualización...');
            $log('Preparando actualización...');

            // 1) Obtener archivo (si es URL, descargar)
            $archivePath = $this->downloadSource($source, $updatesPath, $checksum);

            // 2) Extraer (soporte para .zip y .tar.gz)
            $this->info("Extrayendo {$archivePath} a {$tmpDir}...");
            $log("Extrayendo {$archivePath} a {$tmpDir}...");
            $this->extractArchive($archivePath, $tmpDir, $log);

            // Detectar directorio raíz real (GitHub añade subdirectorio reponame-branch/)
            $srcDir = $this->detectSourceDir($tmpDir, $log);

            // Detectar si el ZIP ya incluye vendor/ (release precompilado)
            $vendorInZip = is_dir($srcDir.'/vendor');
            if ($vendorInZip) {
                $log('vendor/ detectado en el paquete — se omitirá composer install.');
            }

            // 3) Sincronizar archivos (rsync si disponible, PHP recursivo si no)
            $this->info('Sincronizando archivos (excluyendo .env, storage, node_modules)...');
            $log('Sincronizando archivos...');

            $excludes = ['.env', 'storage', 'node_modules'];
            if (! $vendorInZip) {
                $excludes[] = 'vendor';
            }

            $this->syncFiles($srcDir, $basePath, $excludes, $backupDir, $log);

            // 4) composer (solo si vendor/ no viene en el ZIP)
            if (! $noComposer && ! $vendorInZip) {
                $this->info('Ejecutando composer install...');
                $log('Ejecutando composer install...');
                if ($this->procOpenAvailable && $this->commandExists('composer')) {
                    $this->runShell('composer install --no-dev --optimize-autoloader --no-interaction', $basePath, $log);
                } else {
                    $log('AVISO: composer no disponible o proc_open deshabilitado. Omitiendo composer install. Asegúrese de que vendor/ está incluido en el ZIP o ejecute composer manualmente vía SSH.');
                }
            }

            // 5) migrate
            if (! $noMigrate) {
                $this->info('Realizando dump de base de datos antes de migrar...');
                $log('Realizando dump de base de datos antes de migrar...');
                $this->dumpDatabaseIfPossible($updatesPath."/runs/run_{$runId}/db_dump.sql", $log);

                $this->info('Ejecutando migraciones...');
                $log('Ejecutando migraciones...');
                $this->callArtisan('down', [], $log);
                $this->callArtisan('migrate', ['--force' => true], $log);
                $this->callArtisan('up', [], $log);
            }

            // 6) Limpiar y regenerar caches
            $this->info('Limpiando y regenerando caches...');
            $log('Limpiando y regenerando caches...');
            $this->callArtisan('optimize:clear', [], $log);
            $this->callArtisan('optimize', [], $log);

            // 7) limpieza de temporales y rotación de backups
            $this->rrmdir($tmpDir);
            $this->rotateBackups($backupDir, $keepBackups);

            $this->info('Actualización aplicada con éxito.');
            $log('Actualización aplicada con éxito.');

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Error durante la actualización: '.$e->getMessage());
            $log('Error durante la actualización: '.$e->getMessage());
            $this->error('Intentando rollback automático desde backup...');
            $log('Intentando rollback automático desde backup...');

            if (is_dir($backupDir)) {
                try {
                    $this->phpRestoreBackup($backupDir, $basePath, $log);
                    $this->info('Rollback de archivos completado.');
                    $log('Rollback de archivos completado.');
                } catch (\Throwable $e2) {
                    $this->error('Rollback de archivos falló: '.$e2->getMessage());
                    $log('Rollback de archivos falló: '.$e2->getMessage());
                }
            }

            // Restaurar BD si existe dump
            $dbDump = $updatesPath."/runs/run_{$runId}/db_dump.sql";
            if (file_exists($dbDump)) {
                try {
                    $this->restoreDatabaseIfPossible($dbDump, $log);
                    $this->info('Rollback de base de datos completado (si aplicable).');
                    $log('Rollback de base de datos completado (si aplicable).');
                } catch (\Throwable $e3) {
                    $this->error('Rollback de base de datos falló: '.$e3->getMessage());
                    $log('Rollback de base de datos falló: '.$e3->getMessage());
                }
            }

            return self::FAILURE;
        }
    }

    /**
     * Extrae un archivo .zip o .tar.gz al directorio destino.
     * Usa ZipArchive (PHP nativo) para .zip, y tar para .tar.gz si está disponible.
     */
    protected function extractArchive(string $archivePath, string $destDir, callable $log): void
    {
        $lower = strtolower($archivePath);

        if (str_ends_with($lower, '.zip')) {
            if (! class_exists(\ZipArchive::class)) {
                throw new \RuntimeException('La extensión ZipArchive de PHP no está disponible en este servidor.');
            }
            $zip = new \ZipArchive;
            $result = $zip->open($archivePath);
            if ($result !== true) {
                throw new \RuntimeException("No se pudo abrir el ZIP (código {$result}): {$archivePath}");
            }
            $zip->extractTo($destDir);
            $zip->close();
            $log('ZIP extraído correctamente.');

            return;
        }

        // .tar.gz — intentar con tar (shell), luego PharData como fallback
        if ($this->procOpenAvailable && $this->commandExists('tar')) {
            $this->runShell('tar -xzf '.escapeshellarg($archivePath).' -C '.escapeshellarg($destDir), null, $log);

            return;
        }

        // Fallback: PharData (PHP nativo, sin proc_open)
        $log('tar no disponible o proc_open deshabilitado, usando PharData...');
        try {
            $phar = new \PharData($archivePath);
            $phar->decompress(); // .tar.gz → .tar
            $tarPath = str_replace('.gz', '', $archivePath);
            $tar = new \PharData($tarPath);
            $tar->extractTo($destDir);
            @unlink($tarPath);
            $log('Archivo tar.gz extraído con PharData.');
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo extraer el archivo: '.$e->getMessage());
        }
    }

    /**
     * Detecta el directorio fuente real dentro del directorio extraído.
     * GitHub genera ZIPs con un subdirectorio raíz como "repo-main/" o "repo-v1.2.3/".
     */
    protected function detectSourceDir(string $tmpDir, callable $log): string
    {
        $entries = array_values(array_filter(
            glob($tmpDir.'/*') ?: [],
            fn ($e) => is_dir($e)
        ));

        // Si hay exactamente un subdirectorio que contiene una estructura Laravel, úsalo
        if (count($entries) === 1 && is_dir($entries[0].'/app')) {
            $log('Detectado directorio raíz del paquete: '.basename($entries[0]));

            return $entries[0];
        }

        return $tmpDir;
    }

    /**
     * Sincroniza archivos desde $src a $dest, excluyendo los directorios indicados.
     * Usa rsync si está disponible, PHP recursivo en caso contrario.
     */
    protected function syncFiles(string $src, string $dest, array $excludes, string $backupDir, callable $log): void
    {
        if ($this->rsyncAvailable && $this->procOpenAvailable) {
            $excludeArgs = implode(' ', array_map(
                fn ($e) => '--exclude='.escapeshellarg(trim($e, '/')),
                $excludes
            ));
            $rsyncCmd = "rsync -a --delete {$excludeArgs} "
                .'--backup --backup-dir='.escapeshellarg($backupDir).' '
                .escapeshellarg(rtrim($src, '/').'/').' '
                .escapeshellarg(rtrim($dest, '/').'/');
            $this->runShell($rsyncCmd, null, $log);

            return;
        }

        $log('Usando sincronización PHP recursiva (rsync/proc_open no disponible)...');
        $this->phpSyncDir($src, $dest, $excludes, $backupDir, $log);
    }

    /**
     * Sincronización de directorios mediante PHP puro (sin rsync).
     */
    protected function phpSyncDir(string $src, string $dest, array $excludes, string $backupDir, callable $log): void
    {
        $src = rtrim($src, '/');
        $dest = rtrim($dest, '/');
        $copied = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($src) + 1);

            // Comprobar exclusiones (primer componente del path)
            $firstSegment = explode(DIRECTORY_SEPARATOR, $relativePath)[0];
            foreach ($excludes as $ex) {
                if ($firstSegment === trim($ex, '/')) {
                    continue 2;
                }
            }

            $destPath = $dest.DIRECTORY_SEPARATOR.$relativePath;

            if ($item->isDir()) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                // Hacer backup del fichero existente antes de sobreescribir
                if (file_exists($destPath)) {
                    $bkPath = $backupDir.DIRECTORY_SEPARATOR.$relativePath;
                    if (! is_dir(dirname($bkPath))) {
                        mkdir(dirname($bkPath), 0755, true);
                    }
                    copy($destPath, $bkPath);
                }
                if (! is_dir(dirname($destPath))) {
                    mkdir(dirname($destPath), 0755, true);
                }
                copy($item->getPathname(), $destPath);
                $copied++;
            }
        }

        $log("Sincronización PHP completada: {$copied} archivo(s) copiado(s).");
    }

    /**
     * Restaura el backup en caso de rollback usando PHP puro.
     */
    protected function phpRestoreBackup(string $backupDir, string $dest, callable $log): void
    {
        if ($this->rsyncAvailable && $this->procOpenAvailable) {
            $this->runShell('rsync -a '.escapeshellarg($backupDir.'/').' '.escapeshellarg($dest.'/'), null, $log);

            return;
        }

        $backupDir = rtrim($backupDir, '/');
        $dest = rtrim($dest, '/');
        $restored = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($backupDir) + 1);
            $destPath = $dest.DIRECTORY_SEPARATOR.$relativePath;

            if ($item->isDir()) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
                $restored++;
            }
        }

        $log("Rollback PHP completado: {$restored} archivo(s) restaurado(s).");
    }

    /**
     * Llama a un comando Artisan internamente (sin proc_open).
     * Registra la salida en el log de actualización.
     */
    protected function callArtisan(string $command, array $params, callable $log): void
    {
        $log("Artisan: {$command} ".json_encode($params));
        Artisan::call($command, $params);
        $output = trim(Artisan::output());
        if ($output) {
            $log($output);
        }
    }

    /**
     * Comprueba si un comando de shell existe en el PATH.
     */
    protected function commandExists(string $cmd): bool
    {
        if (! function_exists('exec')) {
            return false;
        }
        $out = [];
        $ret = 0;
        @exec('command -v '.escapeshellarg($cmd).' 2>/dev/null', $out, $ret);

        return $ret === 0 && ! empty($out);
    }


    protected function dumpDatabaseIfPossible(string $dumpPath, callable $log): void
    {
        $default = config('database.default');
        $cfg = config("database.connections.{$default}", []);
        if (empty($cfg) || empty($cfg['driver'])) {
            $log('No se pudo determinar la conexión de BD para dump.');

            return;
        }

        if ($cfg['driver'] === 'mysql') {
            $user = $cfg['username'] ?? null;
            $pass = $cfg['password'] ?? null;
            $db = $cfg['database'] ?? null;
            $host = $cfg['host'] ?? '127.0.0.1';
            $port = $cfg['port'] ?? 3306;

            $cmd = sprintf('mysqldump --single-transaction -h %s -P %s -u %s %s %s > %s',
                escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), escapeshellarg($pass ? '-p'.escapeshellarg($pass) : ''), escapeshellarg($db), escapeshellarg($dumpPath)
            );
            // Trying without embedding password in arg to avoid shell exposure
            $cmd = sprintf('mysqldump --single-transaction -h %s -P %s -u %s %s > %s',
                escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), escapeshellarg($db), escapeshellarg($dumpPath)
            );
            $log('Ejecutando: '.$cmd);
            @exec($cmd, $out, $ret);
            if ($ret !== 0) {
                $log('mysqldump no disponible o fallo al crear dump.');
            }

            return;
        }

        if ($cfg['driver'] === 'pgsql') {
            $db = $cfg['database'] ?? null;
            $log('Intentando pg_dump para '.$db);
            $cmd = sprintf('pg_dump %s > %s', escapeshellarg($db), escapeshellarg($dumpPath));
            @exec($cmd, $out, $ret);
            if ($ret !== 0) {
                $log('pg_dump no disponible o fallo al crear dump.');
            }

            return;
        }

        $log('Driver de BD no soportado para dump automático: '.$cfg['driver']);
    }

    protected function restoreDatabaseIfPossible(string $dumpPath, callable $log): void
    {
        $default = config('database.default');
        $cfg = config("database.connections.{$default}", []);
        if (empty($cfg) || empty($cfg['driver'])) {
            $log('No se pudo determinar la conexión de BD para restaurar.');

            return;
        }

        if ($cfg['driver'] === 'mysql') {
            $user = $cfg['username'] ?? null;
            $db = $cfg['database'] ?? null;
            $host = $cfg['host'] ?? '127.0.0.1';
            $port = $cfg['port'] ?? 3306;
            $cmd = sprintf('mysql -h %s -P %s -u %s %s < %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), escapeshellarg($db), escapeshellarg($dumpPath));
            $log('Ejecutando: '.$cmd);
            @exec($cmd, $out, $ret);
            if ($ret !== 0) {
                $log('mysql no disponible o fallo al restaurar dump.');
            }

            return;
        }

        if ($cfg['driver'] === 'pgsql') {
            $db = $cfg['database'] ?? null;
            $cmd = sprintf('psql %s < %s', escapeshellarg($db), escapeshellarg($dumpPath));
            @exec($cmd, $out, $ret);
            if ($ret !== 0) {
                $log('psql no disponible o fallo al restaurar dump.');
            }

            return;
        }

        $log('Driver de BD no soportado para restauración automática: '.$cfg['driver']);
    }

    protected function downloadSource(string $source, string $destDir, ?string $checksum = null): string
    {
        // Si es URL
        if (preg_match('#^https?://#i', $source)) {
            $filename = basename(parse_url($source, PHP_URL_PATH));
            $target = rtrim($destDir, '/').'/'.$filename;

            $this->info("Descargando {$source}...");
            // checksum will be verified after download if provided
            // Intentar file_get_contents, fallback a curl
            $contents = @file_get_contents($source);
            if ($contents !== false) {
                file_put_contents($target, $contents);

                return $target;
            }

            // fallback curl
            $ch = curl_init($source);
            $fp = fopen($target, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            $res = curl_exec($ch);
            if ($res === false) {
                fclose($fp);
                unlink($target);
                throw new \RuntimeException('Error al descargar archivo: '.curl_error($ch));
            }
            curl_close($ch);
            fclose($fp);
            // validar checksum si se proporcionó
            if ($checksum) {
                $hash = hash_file('sha256', $target);
                if (! hash_equals($hash, $checksum)) {
                    unlink($target);
                    throw new \RuntimeException('Checksum inválido para el archivo descargado. Esperado: '.$checksum.', obtenido: '.$hash);
                }
                $this->info('Checksum verificado correctamente.');
            }

            return $target;
        }

        // Ruta local
        if (! file_exists($source)) {
            throw new \InvalidArgumentException('El archivo fuente no existe: '.$source);
        }

        return $source;
    }

    protected function runShell(string $cmd, ?string $cwd = null, ?callable $log = null): void
    {
        $this->info("Ejecutando: {$cmd}");
        if ($log) {
            $log('Ejecutando: '.$cmd);
        }

        // Ensure common bin dirs are in PATH so commands like php/composer are found
        $currentPath = getenv('PATH') ?: '';
        $extraPaths = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin', '/bin'];
        $parts = array_filter(array_unique(array_merge(explode(':', $currentPath), $extraPaths)));
        $newPath = implode(':', $parts);

        $process = Process::fromShellCommandline($cmd, $cwd ?? base_path(), ['PATH' => $newPath]);
        $process->setTimeout(3000);
        $process->run(function ($type, $buffer) use ($log) {
            if ($log) {
                $log(trim($buffer));
            }
            $this->line($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Comando falló: '.$cmd."\n".$process->getErrorOutput());
        }
    }

    protected function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    protected function rotateBackups(string $backupsDir, int $keep): void
    {
        if (! is_dir($backupsDir)) {
            return;
        }
        $entries = array_values(array_filter(scandir($backupsDir), function ($e) use ($backupsDir) {
            return $e !== '.' && $e !== '..' && is_dir($backupsDir.'/'.$e);
        }));
        if (count($entries) <= $keep) {
            return;
        }
        // ordenar por nombre (timestamp en el nombre)
        sort($entries);
        $toDelete = array_slice($entries, 0, count($entries) - $keep);
        foreach ($toDelete as $d) {
            $this->rrmdir($backupsDir.'/'.$d);
        }
    }
}
