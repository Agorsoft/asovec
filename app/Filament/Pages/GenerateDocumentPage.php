<?php

namespace App\Filament\Pages;

use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Quota;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GenerateDocumentPage extends Page implements HasForms
{
    use InteractsWithForms;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;
    protected static ?string $navigationLabel = 'Imprimir plantilla';

    protected static ?string $title = 'Generar documento desde plantilla';

    protected static UnitEnum|string|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.generate-document';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Plantilla de documento')
                    ->schema([
                        Select::make('template_id')
                            ->label('Plantilla')
                            ->options(
                                DocumentTemplate::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (DocumentTemplate $t) => [
                                        $t->id => $t->name.' ('.$t->type->getLabel().')',
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->live()
                            ->helperText('Selecciona la plantilla DOCX que quieres usar.'),
                    ]),

                Section::make('Datos de generación')
                    ->schema([
                        Select::make('member_id')
                            ->label('Socio')
                            ->required()
                            ->searchable()
                            ->live()
                            ->options(
                                Member::orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => $m->last_name.', '.$m->first_name,
                                    ])
                            )
                            ->helperText('Selecciona el socio para el que se generará el documento.'),

                        Select::make('quota_id')
                            ->label('Cuota')
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get): bool => $this->templateHasQuotaVariables($get('template_id')))
                            ->required(fn (Get $get): bool => $this->templateHasQuotaVariables($get('template_id')))
                            ->options(fn (Get $get): array => $get('member_id')
                                ? Quota::with('quotaType')
                                    ->where('member_id', $get('member_id'))
                                    ->orderByDesc('period_start')
                                    ->get()
                                    ->mapWithKeys(fn (Quota $q): array => [
                                        $q->id => implode(' — ', array_filter([
                                            $q->quotaType?->name,
                                            $q->period_start?->format('d/m/Y').' – '.$q->period_end?->format('d/m/Y'),
                                            number_format((float) $q->amount, 2, ',', '.').' €',
                                            $q->status?->label(),
                                        ])),
                                    ])
                                    ->toArray()
                                : []
                            )
                            ->helperText('Selecciona la cuota a la que hace referencia el documento.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadDocument')
                ->label('Descargar documento')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->action(function (): void {
                    $url = $this->buildDownloadUrl();

                    if ($url === null) {
                        return;
                    }

                    $this->js("window.location.href = '".addslashes($url)."'");
                }),
        ];
    }

    private function buildDownloadUrl(): ?string
    {
        $state = $this->form->getState();

        if (empty($state['template_id'])) {
            Notification::make()
                ->title('Selecciona una plantilla')
                ->warning()
                ->send();

            return null;
        }

        if (empty($state['member_id'])) {
            Notification::make()
                ->title('Selecciona un socio')
                ->warning()
                ->send();

            return null;
        }

        if ($this->templateHasQuotaVariables($state['template_id']) && empty($state['quota_id'])) {
            Notification::make()
                ->title('Esta plantilla requiere seleccionar una cuota')
                ->warning()
                ->send();

            return null;
        }

        $params = [
            'template_id' => $state['template_id'],
            'member_id' => $state['member_id'],
        ];

        if (! empty($state['quota_id'])) {
            $params['quota_id'] = $state['quota_id'];
        }

        return route('document.template.generate', $params);
    }

    private function templateHasQuotaVariables(mixed $templateId): bool
    {
        if (! filled($templateId)) {
            return false;
        }

        $template = DocumentTemplate::find((int) $templateId);

        if (! $template) {
            return false;
        }

        foreach ($template->detected_variables ?? [] as $var) {
            if (str_starts_with($var, 'quota.') || str_starts_with($var, 'quota_type.')) {
                return true;
            }
        }

        return false;
    }
}
