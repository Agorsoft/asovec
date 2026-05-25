<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Ver evento';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => EventResource::getUrl('index'))
                ->color('gray'),
            EditAction::make()
                ->label('Editar'),
            Action::make('exportEventRegistrations')
                ->label('Exportar inscripciones')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    $event = $this->record->loadMissing(['registrations.member', 'registrations.sponsorMember']);

                    $registrations = $event->registrations
                        ->sortBy('registration_sequence')
                        ->values();

                    return response()->streamDownload(function () use ($event, $registrations): void {
                        $stream = fopen('php://output', 'w');

                        if ($stream === false) {
                            return;
                        }

                        // UTF-8 BOM for Excel compatibility with accents.
                        fwrite($stream, "\xEF\xBB\xBF");

                        fputcsv($stream, ['Evento', trim((string) $event->event_code.' - '.(string) $event->name)], ';');
                        fputcsv($stream, ['Tipo', (string) ($event->event_type?->value ?? '—')], ';');
                        fputcsv($stream, ['Fecha inicio', (string) ($event->start_date?->format('d/m/Y H:i') ?? '—')], ';');
                        fputcsv($stream, [], ';');

                        fputcsv($stream, [
                            'Nº inscripción',
                            'Participante',
                            'Tipo',
                            'Patrocinador',
                            'Estado',
                            'Base',
                            'Recargo',
                            'Total',
                            'Método pago',
                            'Pagada',
                            'Notas',
                        ], ';');

                        /** @var Registration $registration */
                        foreach ($registrations as $registration) {
                            $base = (float) $registration->amount;
                            $surcharge = (float) $registration->surcharge_amount;

                            fputcsv($stream, [
                                $registration->registration_number,
                                $registration->member?->full_name,
                                $registration->registration_type?->value,
                                $registration->sponsorMember?->full_name,
                                $registration->status?->value,
                                number_format($base, 2, '.', ''),
                                number_format($surcharge, 2, '.', ''),
                                number_format($base + $surcharge, 2, '.', ''),
                                $registration->payment_method,
                                $registration->paid_at?->format('d/m/Y H:i'),
                                $registration->notes,
                            ], ';');
                        }

                        fclose($stream);
                    }, 'inscripciones_'.($event->event_code ?: 'evento').'_'.now()->format('Ymd_His').'.csv', [
                        'Content-Type' => 'text/csv; charset=utf-8',
                    ]);
                }),
        ];
    }
}
