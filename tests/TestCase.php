<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Si la config está cacheada apuntando a una BD real, los tests con RefreshDatabase
        // ejecutarían migrate:fresh sobre producción. Limpiamos la caché antes de arrancar.
        if (file_exists(base_path('bootstrap/cache/config.php'))) {
            Artisan::call('config:clear');
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
