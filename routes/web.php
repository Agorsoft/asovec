<?php

use App\Http\Controllers\AssociationSepaMandateController;
use App\Http\Controllers\ConsentSheetController;
use App\Http\Controllers\DocumentTemplateGenerateController;
use App\Http\Controllers\HelpPdfController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\LabelPrintController;
use App\Http\Controllers\MemberImportDownloadController;
use App\Http\Controllers\QuotaReceiptController;
use App\Http\Controllers\RegistrationReceiptController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Si la aplicación no está instalada, redirigimos todas las peticiones
// al instalador (excepto las rutas de instalador mismas).
$installed = (bool) config('app.installed');

if (! $installed) {
    Route::get('/install', [InstallerController::class, 'show'])->name('install.show');
    Route::post('/install', [InstallerController::class, 'store'])->name('install.store');

    Route::get('/', function () {
        return redirect()->route('install.show');
    })->name('home');

    // Fallback: cualquier otra ruta redirige al instalador mientras no esté instalada
    Route::fallback(fn () => redirect()->route('install.show'));

    return;
}

// Si ya está instalada la aplicación, bloquear cualquier intento de acceder
// al instalador devolviendo 404 para evitar re-ejecución o exposición.
Route::any('/install/{any?}', fn () => abort(404))->where('any', '.*');

Route::get('/', function () {
    return redirect()->route(
        Auth::check()
            ? 'filament.admin.pages.dashboard'
            : 'filament.admin.auth.login'
    );
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('socios/{member}/consentimiento', [ConsentSheetController::class, 'show'])
        ->name('members.consent.print');
    Route::get('asociacion/{association}/mandato-sepa', AssociationSepaMandateController::class)
        ->name('associations.sepa.print');
    Route::get('socios/importacion/descargar-ejemplo', MemberImportDownloadController::class.'@download')
        ->name('members.import.download-example');
    Route::get('inscripciones/{registration}/recibo', [RegistrationReceiptController::class, 'show'])
        ->name('registrations.receipt.show');
    Route::get('cuotas/{quota}/recibo', [QuotaReceiptController::class, 'show'])
        ->name('quotas.receipt.show');
    Route::get('etiquetas/imprimir', [LabelPrintController::class, 'generate'])
        ->name('labels.print.generate');
    // Endpoint seguro para que la interfaz consulte el estado/logs de una actualización
    Route::get('_update-status/{runId}', [\App\Http\Controllers\UpdateStatusController::class, 'show'])
        ->name('update.status');
    Route::get('documentos/plantilla/generar', DocumentTemplateGenerateController::class)
        ->name('document.template.generate');
    Route::get('ayuda/manual.pdf', HelpPdfController::class)
        ->name('help.pdf');
});

require __DIR__.'/settings.php';
