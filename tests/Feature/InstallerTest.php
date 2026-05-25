<?php

use App\Http\Controllers\InstallerController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

// Restaurar la conexión por defecto tras cada test para que RefreshDatabase
// no intente operar sobre 'temp_install' en el siguiente test.
beforeEach(function () {
    $this->originalDbDefault = config('database.default');
});

afterEach(function () {
    Config::set('database.default', $this->originalDbDefault);
    DB::purge('temp_install');
});

/**
 * Subclase que neutraliza la escritura al .env durante los tests.
 */
class TestableInstallerController extends InstallerController
{
    protected function setEnvironmentValue(array $values): void
    {
        // No-op en tests: evita modificar el archivo .env real.
    }
}

// ---------------------------------------------------------------------------
// show()
// ---------------------------------------------------------------------------

test('show renderiza la vista de instalación', function () {
    $controller = new TestableInstallerController;
    $view = $controller->show();

    expect($view->name())->toBe('install');
});

// ---------------------------------------------------------------------------
// store() – validación
// ---------------------------------------------------------------------------

test('store rechaza una petición vacía con errores de validación', function () {
    $controller = new TestableInstallerController;
    $request = new Request;
    $request->replace([]);

    expect(fn () => $controller->store($request))
        ->toThrow(ValidationException::class);
});

test('store valida los campos de conexión y del administrador', function () {
    $controller = new TestableInstallerController;
    $request = new Request;
    $request->replace([
        'DB_CONNECTION' => 'sqlite',   // conexión presente
        // resto de campos ausentes
    ]);

    try {
        $controller->store($request);
    } catch (ValidationException $e) {
        expect($e->errors())
            ->toHaveKey('DB_HOST')
            ->toHaveKey('admin_name')
            ->toHaveKey('admin_email')
            ->toHaveKey('admin_password');

        return;
    }

    $this->fail('Se esperaba ValidationException');
});

// ---------------------------------------------------------------------------
// store() – fallo de conexión a BD
// ---------------------------------------------------------------------------

test('store devuelve error cuando la conexión a la base de datos falla', function () {
    $controller = new TestableInstallerController;
    $request = new Request;
    $request->replace([
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '9999',          // puerto inalcanzable
        'DB_DATABASE' => 'noexiste',
        'DB_USERNAME' => 'root',
        'DB_PASSWORD' => '',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@test.com',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    // Simular una URL previa para que back() funcione
    $request->headers->set('referer', url('/install'));

    $response = $controller->store($request);

    // Debe redirigir de vuelta con el error 'db'
    expect($response->getStatusCode())->toBe(302)
        ->and(session('errors')?->has('db') ?? $response->getSession()?->get('errors')?->has('db'))
        ->toBeTrue();
})->skip('Requiere sesión HTTP completa; usar test de integración si se habilita el instalador en test env.');

// ---------------------------------------------------------------------------
// store() – flujo completo: usuario admin creado con is_admin = true
// ---------------------------------------------------------------------------

test('store crea el usuario administrador con is_admin true', function () {
    $controller = new TestableInstallerController;

    $request = new Request;
    $request->replace([
        'DB_CONNECTION' => 'sqlite',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_DATABASE' => ':memory:',
        'DB_USERNAME' => 'test',
        'DB_PASSWORD' => '',
        'admin_name' => 'Administrador',
        'admin_email' => 'admin@asovecfilament.test',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $controller->store($request);

    expect(Auth::user())
        ->not->toBeNull()
        ->and(Auth::user()->is_admin)->toBeTrue()
        ->and(Auth::user()->email)->toBe('admin@asovecfilament.test')
        ->and(Auth::user()->email_verified_at)->not->toBeNull();
});

test('store redirige a home tras la instalación correcta', function () {
    $controller = new TestableInstallerController;

    $request = new Request;
    $request->replace([
        'DB_CONNECTION' => 'sqlite',
        'DB_HOST' => 'localhost',
        'DB_PORT' => '3306',
        'DB_DATABASE' => ':memory:',
        'DB_USERNAME' => 'test',
        'DB_PASSWORD' => '',
        'admin_name' => 'Administrador',
        'admin_email' => 'admin2@asovecfilament.test',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
    ]);

    $response = $controller->store($request);

    expect($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe(route('home'));
});
