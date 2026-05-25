<?php

use App\Filament\Pages\FirstMemberImport;
use App\Models\Association;
use App\Models\Member;
use App\Models\QuotaType;
use App\Services\MemberImportService;

beforeEach(function (): void {
    Member::query()->delete();
    QuotaType::query()->delete();
    Association::query()->delete();
});

test('parsea cabeceras en español y delimitador tab para importar socios', function (): void {
    QuotaType::factory()->create();

    $file = tempnam(sys_get_temp_dir(), 'import_members_');

    $content = implode("\t", [
        'NUMERO',
        'NOMBRE',
        'APELLIDOS',
        'DNI',
        'GENERO',
        'FECHA_NACIMIENTO',
        'DIRECCION',
        'CODIGO_POSTAL',
        'LOCALIDAD',
        'PROVINCIA',
        'TELEFONO',
        'EMAIL',
        'METODO_PAGO',
        'FECHA_ALTA',
        'FECHA_ULTIMA_CUOTA',
        'TIPO_PERIODO',
        'NOMBRE_TIPO_CUOTA',
        'IMPORTE_PERSONALIZADO',
        'NOTAS',
    ])."\n";

    $content .= implode("\t", [
        '220',
        'Juan',
        'Garcia Lopez',
        '12345678A',
        'Hombre',
        '15/06/1980',
        'Calle Principal 123',
        '28001',
        'Madrid',
        'Madrid',
        '912345678',
        'juan@example.com',
        'Efectivo',
        '10/05/2026',
        '13/05/2026',
        'Anual',
        '',
        '',
        '',
    ])."\n";

    $content .= implode("\t", [
        '223',
        'Maria',
        'Rodriguez Lopez',
        '87654321B',
        'Mujer',
        '22/03/1985',
        'Avenida Central 456',
        '08002',
        'Barcelona',
        'Barcelona',
        '932345678',
        'maria@example.com',
        'Efectivo',
        '12/05/2026',
        '',
        '',
        '',
        '',
        '',
    ])."\n";

    file_put_contents($file, $content);

    $page = new FirstMemberImport;

    $parseCsv = \Closure::bind(
        fn (string $filePath): array => $this->parseCsv($filePath),
        $page,
        FirstMemberImport::class,
    );

    $rows = $parseCsv($file);
    $result = MemberImportService::import($rows);

    expect($result['imported'])->toBe(2)
        ->and($result['skipped'])->toBe(0)
        ->and($result['errors'])->toBeEmpty()
        ->and(Member::count())->toBe(2)
        ->and(Association::query()->first()?->last_member_number)->toBe(223);

    @unlink($file);
});
