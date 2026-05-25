<?php

namespace App\Filament\Resources\DocumentTemplates\Tables\Actions;

use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\Member;
use App\Models\Quota;
use App\Services\Documents\DocxTemplateRenderer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateDocumentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'generate_document';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Generar')
            ->icon('heroicon-o-document-arrow-down')
            ->color('primary')
            ->schema(fn (DocumentTemplate $record): array => [
                Select::make('member_id')
                    ->label('Socio')
                    ->required()
                    ->searchable()
                    ->live()
                    ->options(
                        fn () => Member::orderBy('last_name')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn (Member $m) => [$m->id => $m->last_name . ', ' . $m->first_name])
                            ->toArray()
                    )
                    ->helperText('Selecciona el socio para el que se generará el documento.'),

                Select::make('quota_id')
                    ->label('Cuota')
                    ->required()
                    ->searchable()
                    ->visible(self::templateHasQuotaVariables($record))
                    ->options(fn (Get $get): array => $get('member_id')
                        ? Quota::with('quotaType')
                            ->where('member_id', $get('member_id'))
                            ->orderByDesc('period_start')
                            ->get()
                            ->mapWithKeys(fn (Quota $q): array => [
                                $q->id => implode(' — ', array_filter([
                                    $q->quotaType?->name,
                                    $q->period_start?->format('d/m/Y') . ' – ' . $q->period_end?->format('d/m/Y'),
                                    number_format((float) $q->amount, 2, ',', '.') . ' €',
                                    $q->status?->label(),
                                ])),
                            ])
                            ->toArray()
                        : []
                    )
                    ->helperText('Selecciona la cuota a la que hace referencia el documento.'),
            ])
            ->action(function (DocumentTemplate $record, array $data): ?StreamedResponse {
                $member = Member::findOrFail($data['member_id']);

                $context = ['member' => $member];

                if (isset($data['quota_id'])) {
                    $quota = Quota::with('quotaType')->findOrFail($data['quota_id']);
                    $context['quota'] = $quota;
                    $context['quota_type'] = $quota->quotaType;
                }

                $renderer = app(DocxTemplateRenderer::class);

                $generation = DocumentGeneration::create([
                    'template_id'  => $record->id,
                    'generated_by' => Auth::id(),
                    'member_id'    => $member->id,
                    'status'       => 'completed',
                ]);

                try {
                    $filename = Str::slug($record->name) . '_' . Str::slug($member->last_name . '_' . $member->first_name) . '_' . Carbon::now()->format('Ymd_His') . '.docx';
                    $bytes = $renderer->render($record, $context);

                    $generation->update(['output_path' => null]);

                    Notification::make()
                        ->title('Documento generado correctamente')
                        ->success()
                        ->send();

                    return response()->streamDownload(
                        fn () => print($bytes),
                        $filename,
                        ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                    );
                } catch (\Throwable $e) {
                    $generation->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('Error al generar el documento')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    $this->halt();

                    return null;
                }
            });
    }

    private static function templateHasQuotaVariables(DocumentTemplate $record): bool
    {
        foreach ($record->detected_variables ?? [] as $var) {
            if (str_starts_with($var, 'quota.') || str_starts_with($var, 'quota_type.')) {
                return true;
            }
        }

        return false;
    }
}
