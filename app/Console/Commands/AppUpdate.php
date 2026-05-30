<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('app:update {source : URL o ruta local al .tar.gz o .zip} {--keep-backups=3} {--no-migrate} {--no-composer} {--checksum=} {--run-id=}')]
#[Description('Descarga y aplica una actualización desde un tar.gz/zip en el servidor')]
class AppUpdate extends Command
{
    public function handle(): int
    {
        $source = $this->argument('source');
        $keepBackups = (int) $this->option('keep-backups');
        $noMigrate = (bool) $this->option('no-migrate');
        $noComposer = (bool) $this->option('no-composer');
        $checksum = $this->option('checksum') ?: null;
        $runId = $this->option('run-id') ?: date('YmdHis').'_'.bin2hex(random_bytes(4));

        $basePath = base_path();
        $updatesPath = storage_path('app/updates');
        if (! is_dir($updatesPath)) {
            mkdir($updatesPath, 0755, true);
        }

        $timestamp = date('YmdHis');
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

        try {
            $this->info('Preparando actualización...');
            $log('Preparando actualización...');

            // 1) Obtener archivo (si es URL, descargar)
            $archivePath = $this->downloadSource($source, $updatesPath, $checksum);

            // 2) Extraer
            $this->info("Extrayendo {$archivePath} a {$tmpDir}...");
            $log("Extrayendo {$archivePath} a {$tmpDir}...");
            $this->runShell('tar -xzf '.escapeshellarg($archivePath).' -C '.escapeshellarg($tmpDir), null, $log);

            // 3) Hacer rsync con backup
            $this->info('Sincronizando archivos (excluyendo .env, storage, vendor, node_modules)...');
            $log('Sincronizando archivos (excluyendo .env, storage, vendor, node_modules)...');
            $rsyncCmd = 'rsync -a --delete '
                ."--exclude='.env' --exclude='storage' --exclude='vendor' --exclude='node_modules' "
                .'--backup --backup-dir='.escapeshellarg($backupDir).' '
                .escapeshellarg(rtrim($tmpDir, '/').'/').' '.escapeshellarg($basePath.'/');

            $this->runShell($rsyncCmd, null, $log);

            // 4) composer
            if (! $noComposer) {
                $this->info('Ejecutando composer install...');
                $log('Ejecutando composer install...');
                $this->runShell('composer install --no-dev --optimize-autoloader --no-interaction', $basePath, $log);
            }

            // 5) migrate
            if (! $noMigrate) {
                $this->info('Realizando dump de base de datos antes de migrar...');
                $log('Realizando dump de base de datos antes de migrar...');
                $this->dumpDatabaseIfPossible($updatesPath."/runs/run_{$runId}/db_dump.sql", $log);

                $this->info('Poniendo aplicación en mantenimiento...');
                $log('Poniendo aplicación en mantenimiento...');
                $this->runShell('php artisan down --no-interaction', $basePath, $log);

                $this->info('Ejecutando migraciones...');
                $log('Ejecutando migraciones...');
                $this->runShell('php artisan migrate --force', $basePath, $log);

                $this->info('Saliendo de modo mantenimiento...');
                $log('Saliendo de modo mantenimiento...');
                $this->runShell('php artisan up', $basePath, $log);
            }

            // 6) cache optimizations
            $this->info('Optimizando caches...');
            $log('Optimizando caches...');
            $this->runShell('php artisan config:cache', $basePath, $log);
            $this->runShell('php artisan route:cache', $basePath, $log);
            $this->runShell('php artisan view:cache', $basePath, $log);
            $this->runShell('php artisan queue:restart', $basePath, $log);

            // 7) limpieza de temporales y rotación de backups
            $this->rrmdir($tmpDir);
            $this->rotateBackups($backupDir, $keepBackups);

            $this->info('Actualización aplicada con éxito.');
            $this->info("Backups guardados en: {$updatesPath}/backups/");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('Error durante la actualización: '.$e->getMessage());
            $log('Error durante la actualización: '.$e->getMessage());
            $this->error('Intentando rollback automático desde backup...');
            $log('Intentando rollback automático desde backup...');

            if (is_dir($backupDir)) {
                try {
                    $this->runShell('rsync -a '.escapeshellarg($backupDir.'/').' '.escapeshellarg($basePath.'/'), null, $log);
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

            $this->line('Para revertir manualmente, puede restaurar desde el backup con:');
            $this->line('rsync -a '.escapeshellarg($backupDir.'/').' '.escapeshellarg($basePath.'/'));
            $log('Rollback manual: rsync -a '.$backupDir.'/ '.$basePath.'/');

            return self::FAILURE;
        }
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
