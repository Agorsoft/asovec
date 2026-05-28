<?php

namespace App\Filament\Pages;

use App\Models\Member;
use App\Services\MemberImportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class FirstMemberImport extends Page implements HasForms
{
    use InteractsWithForms;

    // protected static BackedEnum|string|null $navigationIcon = Heroicon::ArrowDownTray;
    protected static ?string $navigationLabel = 'Primera Importación';

    protected static ?string $title = 'Importar Socios';

    protected static UnitEnum|string|null $navigationGroup = 'Gestión de Socios';

    protected string $view = 'filament.pages.first-member-import';

    public static function shouldRegisterNavigation(): bool
    {
        return Member::count() === 0;
    }

    public ?array $data = [];

    public function mount(): void
    {
        if (Member::count() > 0) {
            redirect()->route('filament.admin.resources.members.index')
                ->with('notification', 'La importación solo se permite si no hay socios registrados.');
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Placeholder::make('info')
                    ->label('Formato de Importación')
                    ->content($this->getImportInstructions())
                    ->html()
                    ->columnSpanFull(),

                FileUpload::make('file')
                    ->label('Archivo CSV')
                    ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                    ->maxSize(5120) // 5 MB
                    ->required()
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    private function getImportInstructions(): string
    {
        return <<<'HTML'
            <div class="space-y-4 text-sm">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="font-semibold text-amber-900">⚠️ Requisito Obligatorio</p>
                    <p class="text-amber-800 mt-1">Debe existir al menos un <strong>Tipo de Cuota</strong> activo en el sistema antes de importar socios.</p>
                </div>

                <div>
                    <p class="font-semibold mb-2">Campos del CSV (19 columnas)</p>
                    <p class="text-gray-700 mb-2">Cabeceras recomendadas en español (también se aceptan cabeceras históricas en inglés).</p>
                    <table class="w-full border-collapse text-xs">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border border-gray-300 px-2 py-1 text-left">Columna</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Tipo</th>
                                <th class="border border-gray-300 px-2 py-1 text-left">Descripción</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs">
                            <tr class="bg-blue-50">
                                <td class="border border-gray-300 px-2 py-1"><strong>member_number</strong></td>
                                <td class="border border-gray-300 px-2 py-1">Obligatorio</td>
                                <td class="border border-gray-300 px-2 py-1">Número único del socio (ej: 0001)</td>
                            </tr>
                            <tr class="bg-blue-50">
                                <td class="border border-gray-300 px-2 py-1"><strong>first_name</strong></td>
                                <td class="border border-gray-300 px-2 py-1">Obligatorio</td>
                                <td class="border border-gray-300 px-2 py-1">Nombre del socio</td>
                            </tr>
                            <tr class="bg-blue-50">
                                <td class="border border-gray-300 px-2 py-1"><strong>last_name</strong></td>
                                <td class="border border-gray-300 px-2 py-1">Obligatorio</td>
                                <td class="border border-gray-300 px-2 py-1">Apellido del socio</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">dni</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">DNI/NIF (ej: 12345678A)</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">gender</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Género: <code class="bg-gray-100 px-1">Hombre</code>, <code class="bg-gray-100 px-1">Mujer</code>, <code class="bg-gray-100 px-1">NoDefinido</code> (por defecto)</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">birth_date</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Fecha nacimiento en formato DD/MM/YYYY (también se acepta YYYY-MM-DD)</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">address</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Calle y número</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">postal_code</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Código postal</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">city</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Localidad</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">province</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Provincia</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">phone</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Teléfono</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">email</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Email válido</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">payment_method</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Método pago: <code class="bg-gray-100 px-1">Efectivo</code>, <code class="bg-gray-100 px-1">Transferencia</code>, <code class="bg-gray-100 px-1">Bizum</code>, <code class="bg-gray-100 px-1">Tarjeta</code>, <code class="bg-gray-100 px-1">Domiciliación</code></td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">joined_at</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Fecha de alta (DD/MM/YYYY o YYYY-MM-DD). Si no se especifica, se usa la fecha de la última cuota o la fecha actual.</td>
                            </tr>
                            <tr class="bg-green-50">
                                <td class="border border-gray-300 px-2 py-1"><strong>last_paid_date</strong></td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Fecha última cuota pagada (DD/MM/YYYY o YYYY-MM-DD). Si se omite, el socio queda con estado <strong>Pendiente</strong>. Si se incluye, estado es <strong>Activo</strong>.</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">period_type</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Período cuota: <code class="bg-gray-100 px-1">Anual</code>, <code class="bg-gray-100 px-1">Semestral</code>, <code class="bg-gray-100 px-1">Trimestral</code>, <code class="bg-gray-100 px-1">Mensual</code>. Si no se especifica, se usa el período del Tipo de Cuota encontrado.</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">quota_type_name</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Nombre exacto del Tipo de Cuota. Ej: "CUOTA ANUAL SOCIO 2025". Debe coincidir exactamente con el nombre en el sistema.</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">custom_amount</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Importe de cuota personalizado. Si se omite, se usa el importe por defecto del Tipo de Cuota.</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">notes</td>
                                <td class="border border-gray-300 px-2 py-1">Opcional</td>
                                <td class="border border-gray-300 px-2 py-1">Notas adicionales del socio</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <p class="font-semibold text-blue-900 mb-2">📋 Lógica de Búsqueda de Tipo de Cuota</p>
                    <ol class="text-blue-800 list-decimal list-inside space-y-1">
                        <li>Si <strong>quota_type_name</strong> se especifica → Se busca por nombre exacto</li>
                        <li>Si <strong>period_type</strong> se especifica → Se busca por período (primer coincidente)</li>
                        <li>Si ninguno se especifica → Se usa el Tipo de Cuota activo por defecto (primero en orden)</li>
                    </ol>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <p class="font-semibold text-green-900 mb-2">✓ Estados del Socio</p>
                    <ul class="text-green-800 list-disc list-inside space-y-1">
                        <li><strong>Pendiente</strong>: Socio sin última cuota pagada (last_paid_date vacío)</li>
                        <li><strong>Activo</strong>: Socio con al menos una cuota pagada (last_paid_date especificado)</li>
                    </ul>
                </div>
            </div>
            HTML;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('download_example')
                ->label('Descargar Ejemplo')
                ->icon(Heroicon::ArrowDownTray)
                ->url(route('members.import.download-example'), shouldOpenInNewTab: true),
            Action::make('import')
                ->label('Importar')
                ->icon(Heroicon::Check)
                ->submit('import')
                ->color('success'),
        ];
    }

    public function import(): void
    {
        $data = $this->form->getState();

        if (! filled($data['file'] ?? null)) {
            Notification::make()
                ->title('Error')
                ->body('Por favor seleccione un archivo CSV.')
                ->danger()
                ->send();

            return;
        }

        try {
            $filePath = $this->resolveUploadedFilePath($data['file']);

            if (! $filePath || ! file_exists($filePath)) {
                throw new \Exception('El archivo no fue encontrado.');
            }

            $rows = $this->parseCsv($filePath);

            if (empty($rows)) {
                throw new \Exception('El archivo CSV está vacío.');
            }

            $result = MemberImportService::import($rows);

            $message = sprintf(
                'Importación completada: %d socios importados',
                $result['imported']
            );

            if ($result['skipped'] > 0) {
                $message .= sprintf(', %d saltados', $result['skipped']);
            }

            if (! empty($result['errors'])) {
                $message .= sprintf(', %d errores', count($result['errors']));
            }

            Notification::make()
                ->title('Importación Exitosa')
                ->body($message)
                ->success()
                ->send();

            if (! empty($result['errors'])) {
                Notification::make()
                    ->title('Errores Detectados')
                    ->body($this->formatErrors($result['errors']))
                    ->warning()
                    ->send();
            }

            $this->redirect(route('filament.admin.resources.members.index'));
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Validación Fallida')
                ->body($e->errors()['import'][0] ?? 'Error desconocido')
                ->danger()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al Importar')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  TemporaryUploadedFile|array<int, TemporaryUploadedFile|string>|string|null  $file
     */
    private function resolveUploadedFilePath(TemporaryUploadedFile|array|string|null $file): ?string
    {
        if ($file instanceof TemporaryUploadedFile) {
            return $file->getRealPath() ?: null;
        }

        if (is_array($file)) {
            $first = reset($file);

            return $this->resolveUploadedFilePath($first ?: null);
        }

        if (! is_string($file) || blank($file)) {
            return null;
        }

        $disk = config('filament.default_filesystem_disk', config('filesystems.default'));
        $path = Storage::disk((string) $disk)->path($file);

        if (file_exists($path)) {
            return $path;
        }

        $livewireTmpPath = storage_path('app/livewire-tmp/'.$file);

        if (file_exists($livewireTmpPath)) {
            return $livewireTmpPath;
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsv(string $filePath): array
    {
        $rows = [];
        $headers = null;

        if (($handle = fopen($filePath, 'r')) === false) {
            throw new \Exception('No se puede abrir el archivo CSV.');
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return [];
        }

        $delimiter = $this->detectCsvDelimiter($firstLine);

        rewind($handle);

        $lineNumber = 0;
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                $headers = array_map(fn (string $header): string => $this->normalizeCsvHeader($header), $data);

                continue;
            }

            if (empty(array_filter($data))) {
                continue;
            }

            $row = array_combine($headers, $data);
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function detectCsvDelimiter(string $line): string
    {
        $line = ltrim($line, "\xEF\xBB\xBF");

        $delimiters = [
            "\t",
            ';',
            ',',
            '|',
        ];

        $bestDelimiter = ',';
        $bestScore = -1;

        foreach ($delimiters as $delimiter) {
            $score = substr_count($line, $delimiter);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function normalizeCsvHeader(string $header): string
    {
        $normalized = Str::of($header)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        return match ($normalized) {
            'numero', 'numero_socio', 'n_socio', 'nro_socio', 'member_number' => 'member_number',
            'nombre', 'first_name' => 'first_name',
            'apellidos', 'apellido', 'last_name' => 'last_name',
            'dni' => 'dni',
            'genero', 'sexo', 'gender' => 'gender',
            'fecha_nacimiento', 'birth_date' => 'birth_date',
            'direccion', 'address' => 'address',
            'codigo_postal', 'cp', 'postal_code' => 'postal_code',
            'localidad', 'poblacion', 'city' => 'city',
            'provincia', 'province' => 'province',
            'telefono', 'movil', 'phone' => 'phone',
            'email', 'correo', 'correo_electronico' => 'email',
            'metodo_pago', 'payment_method' => 'payment_method',
            'fecha_alta', 'joined_at' => 'joined_at',
            'fecha_ultima_cuota', 'last_paid_date' => 'last_paid_date',
            'tipo_periodo', 'period_type' => 'period_type',
            'nombre_tipo_cuota', 'quota_type_name' => 'quota_type_name',
            'importe_personalizado', 'custom_amount' => 'custom_amount',
            'notas', 'notes' => 'notes',
            default => $normalized,
        };
    }

    /**
     * @param  array<int, array{row: int, error: string}>  $errors
     */
    private function formatErrors(array $errors): string
    {
        $formatted = "Filas con errores:\n\n";

        foreach (array_slice($errors, 0, 5) as $error) {
            $formatted .= sprintf("Fila %d: %s\n", $error['row'], $error['error']);
        }

        if (count($errors) > 5) {
            $formatted .= sprintf("\n... y %d errores más", count($errors) - 5);
        }

        return $formatted;
    }
}
