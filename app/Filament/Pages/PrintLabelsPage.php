<?php

namespace App\Filament\Pages;

use App\DataTransferObjects\Labels\PrintJobConfig;
use App\Enums\LabelField;
use App\Models\LabelTemplate;
use App\Models\Member;
use App\Services\Labels\LabelPdfGenerator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class PrintLabelsPage extends Page implements HasForms
{
    use InteractsWithForms;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;
    protected static ?string $navigationLabel = 'Imprimir etiquetas';
    protected static ?string $title = 'Imprimir etiquetas postales';
    protected static UnitEnum|string|null $navigationGroup = 'Reportes';
    protected static ?int $navigationSort = 30;
    protected string $view = 'filament.pages.print-labels';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fields' => LabelField::defaultSelection(),
            'start_position' => 1,
            'test_sheet' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Plantilla de etiquetas')
                    ->schema([
                        Select::make('label_template_id')
                            ->label('Formato de etiqueta')
                            ->options(
                                LabelTemplate::orderBy('brand')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (LabelTemplate $t) => [$t->id => $t->display_name])
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('Selecciona el formato que coincida con tu hoja de etiquetas adhesivas'),
                    ]),

                Section::make('Socios a imprimir')
                    ->schema([
                        Select::make('member_ids')
                            ->label('Socios')
                            ->multiple()
                            ->options(
                                Member::orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => $m->member_number_formatted . ' — ' . $m->full_name,
                                    ])
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Deja vacío para imprimir todos los socios (ordenados por apellido)')
                            ->columnSpanFull(),
                    ]),

                Section::make('Contenido de las etiquetas')
                    ->schema([
                        CheckboxList::make('fields')
                            ->label('Campos a incluir')
                            ->options(LabelField::options())
                            ->required()
                            ->columns(2)
                            ->helperText('Las líneas vacías para un socio se omiten automáticamente'),
                    ]),

                Section::make('Opciones de impresión')
                    ->schema([
                        TextInput::make('start_position')
                            ->label('Posición de inicio')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->helperText('Primera posición disponible en la hoja (útil si la hoja ya tiene etiquetas usadas)'),

                        Toggle::make('test_sheet')
                            ->label('Hoja de prueba')
                            ->helperText('Genera una hoja con bordes y coordenadas visibles para calibrar la impresora'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPdf')
                ->label('Previsualizar PDF')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->action(function () {
                    $url = $this->buildPdfUrl(preview: true);
                    if ($url === null) {
                        return;
                    }
                    $this->js("window.open('" . addslashes($url) . "', '_blank')");
                }),

            Action::make('downloadPdf')
                ->label('Descargar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(function () {
                    $url = $this->buildPdfUrl(preview: false);
                    if ($url === null) {
                        return;
                    }
                    $this->js("window.location.href = '" . addslashes($url) . "'");
                }),
        ];
    }

    private function buildPdfUrl(bool $preview): ?string
    {
        $state = $this->form->getState();

        if (empty($state['label_template_id'])) {
            Notification::make()
                ->title('Selecciona una plantilla de etiquetas')
                ->warning()
                ->send();

            return null;
        }

        if (empty($state['fields'])) {
            Notification::make()
                ->title('Selecciona al menos un campo para imprimir')
                ->warning()
                ->send();

            return null;
        }

        $params = [
            'template_id' => $state['label_template_id'],
            'fields' => implode(',', $state['fields']),
            'start' => max(1, (int) ($state['start_position'] ?? 1)),
            'test' => (int) ($state['test_sheet'] ?? false),
            'preview' => (int) $preview,
        ];

        if (! empty($state['member_ids'])) {
            $params['members'] = implode(',', $state['member_ids']);
        }

        return route('labels.print.generate', $params);
    }
}
