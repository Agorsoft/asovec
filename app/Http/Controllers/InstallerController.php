<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallerController extends Controller
{
    public function show()
    {
        return view('install');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'DB_CONNECTION' => 'required|string',
            'DB_HOST' => 'required|string',
            'DB_PORT' => 'required|string',
            'DB_DATABASE' => 'required|string',
            'DB_USERNAME' => 'required|string',
            'DB_PASSWORD' => 'nullable|string',

            // Admin user fields
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|confirmed|min:8',
        ]);

        // Sólo persistir temporalmente los datos de conexión y el flag de instalación.
        $this->setEnvironmentValue([
            'DB_CONNECTION' => $data['DB_CONNECTION'],
            'DB_HOST' => $data['DB_HOST'],
            'DB_PORT' => $data['DB_PORT'],
            'DB_DATABASE' => $data['DB_DATABASE'],
            'DB_USERNAME' => $data['DB_USERNAME'],
            'DB_PASSWORD' => $data['DB_PASSWORD'] ?? '',
            'APP_INSTALLED' => 'false',
        ]);

        // Configure runtime DB connection to test credentials
        Config::set('database.connections.temp_install', [
            'driver' => $data['DB_CONNECTION'],
            'host' => $data['DB_HOST'],
            'port' => $data['DB_PORT'],
            'database' => $data['DB_DATABASE'],
            'username' => $data['DB_USERNAME'],
            'password' => $data['DB_PASSWORD'] ?? null,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);

        try {
            DB::connection('temp_install')->getPdo();
        } catch (\Exception $e) {
            return back()->withErrors(['db' => 'No se pudo conectar a la base de datos: '.$e->getMessage()])->withInput();
        }

        // Persist connection to .env
        $this->setEnvironmentValue([
            'DB_CONNECTION' => $data['DB_CONNECTION'],
            'DB_HOST' => $data['DB_HOST'],
            'DB_PORT' => $data['DB_PORT'],
            'DB_DATABASE' => $data['DB_DATABASE'],
            'DB_USERNAME' => $data['DB_USERNAME'],
            'DB_PASSWORD' => $data['DB_PASSWORD'] ?? '',
            'APP_INSTALLED' => 'true',
        ]);

        // Asegurar que Artisan usa la conexión que acabamos de probar.
        // Registramos la conexión como predeterminada para esta ejecución
        // y limpiamos la configuración cacheada antes de migrar.
        Config::set('database.default', 'temp_install');

        // Limpiar config cache en runtime para evitar config cacheada
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
        } catch (\Throwable $e) {
            // ignore
        }

        // Run migrations sobre la conexión temporal
        Artisan::call('migrate', ['--force' => true, '--database' => 'temp_install']);

        // Crear enlace simbólico public/storage → storage/app/public
        try {
            Artisan::call('storage:link');
        } catch (\Throwable) {
            // Ignorar si ya existe el enlace o el directorio
        }

        // Crear el usuario administrador utilizando la conexión por defecto
        // (hemos establecido 'temp_install' como conexión por defecto para esta ejecución)
        if (! empty($data['admin_email'])) {
            $user = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'is_admin' => true,
            ]);

            // Marcar email verificado y hacer login
            $user->email_verified_at = now();
            $user->save();

            Auth::login($user);
        }

        return redirect()->route('home');
    }

    protected function setEnvironmentValue(array $values)
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
        }

        $env = file_get_contents($envPath);

        foreach ($values as $key => $value) {
            $escaped = preg_quote($key, '/');
            $formatted = $this->formatEnvValue((string) $value);

            if (preg_match("/^{$escaped}=.*/m", $env)) {
                $env = preg_replace("/^{$escaped}=.*/m", "{$key}={$formatted}", $env);
            } else {
                $env .= PHP_EOL."{$key}={$formatted}";
            }
        }

        file_put_contents($envPath, $env);
    }

    /**
     * Envuelve el valor en comillas dobles si contiene caracteres especiales
     * que romperían el formato .env: espacios, #, =, comillas o backslashes.
     */
    protected function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Si ya es un valor booleano o numérico simple, no necesita comillas.
        if (in_array($value, ['true', 'false', 'null'], strict: true) || is_numeric($value)) {
            return $value;
        }

        // Si contiene caracteres que rompen el .env, envolver en comillas dobles.
        if (preg_match('/[\s#"\'=\\\\]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
