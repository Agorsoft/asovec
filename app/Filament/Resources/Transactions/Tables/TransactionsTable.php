<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\EventType;
use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Models\Event;
use App\Models\Quota;
use App\Models\Registration;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('exportTransactions')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        CheckboxList::make('optional_columns')
                            ->label('Columnas opcionales')
                            ->options([
                                'payment_method' => 'Método Pago',
                                'receipt_number' => 'Número Recibo',
                                'notes' => 'Notas',
                            ])
                            ->columns(1),
                    ])
                    ->modalHeading('Exportar transacciones')
                    ->modalSubmitActionLabel('Descargar CSV')
                    ->action(function (array $data, $livewire): StreamedResponse {
                        $selectedOptionalColumns = collect($data['optional_columns'] ?? [])
                            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                            ->values()
                            ->all();

                        $headers = [
                            'Fecha',
                            'Tipo',
                            'Importe',
                            'Origen',
                            'Descripción',
                        ];

                        foreach ($selectedOptionalColumns as $column) {
                            $headers[] = match ($column) {
                                'payment_method' => 'Método Pago',
                                'receipt_number' => 'Número Recibo',
                                'notes' => 'Notas',
                                default => $column,
                            };
                        }

                        $query = $livewire->getTableQueryForExport();

                        $transactions = $query->get();

                        return response()->streamDownload(function () use ($headers, $transactions, $selectedOptionalColumns): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            // UTF-8 BOM for Excel compatibility with accents.
                            fwrite($stream, "\xEF\xBB\xBF");

                            fputcsv($stream, $headers, ';');

                            /** @var Transaction $transaction */
                            foreach ($transactions as $transaction) {
                                $row = [
                                    $transaction->date?->format('d/m/Y'),
                                    $transaction->type?->value,
                                    number_format((float) $transaction->amount, 2, '.', ''),
                                    $transaction->sourceLabel(),
                                    $transaction->description,
                                ];

                                foreach ($selectedOptionalColumns as $column) {
                                    $row[] = match ($column) {
                                        'payment_method' => $transaction->payment_method,
                                        'receipt_number' => $transaction->receipt_number,
                                        'notes' => $transaction->notes,
                                        default => null,
                                    };
                                }

                                fputcsv($stream, $row, ';');
                            }

                            fclose($stream);
                        }, 'transacciones_export_'.now()->format('Ymd_His').'.csv', [
                            'Content-Type' => 'text/csv; charset=utf-8',
                        ]);
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->root()
                ->with('childTransactions')
            )
            ->defaultSort('date', direction: 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (TransactionType $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('source_label')
                    ->label('Origen')
                    ->state(fn (Transaction $record): string => $record->sourceLabel())
                    ->badge(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('child_breakdown')
                    ->label('Desglose')
                    ->state(fn (Transaction $record): array => $record->childTransactions
                        ->map(fn (Transaction $child): string => $child->sourceLabel().' · '.number_format((float) $child->amount, 2, ',', '.').' EUR')
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                TextColumn::make('payment_method')
                    ->label('Método')
                    ->toggleable(),
                TextColumn::make('receipt_number')
                    ->label('Recibo')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('origen')
                    ->label('Origen')
                    ->form([
                        Select::make('group')
                            ->label('Origen')
                            ->options([
                                'all' => 'Todos',
                                'manual' => 'Manual',
                                'cuotas' => 'Cuotas',
                                'evento' => 'Evento',
                                'venta_especial' => 'Venta Especial',
                            ])
                            ->default('all'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['group'] ?? 'all') {
                            'manual' => $query->where('source_type', TransactionSourceType::Manual->value),
                            'cuotas' => $query->where('reference_type', Quota::class),
                            'evento' => $query
                                ->where('reference_type', Registration::class)
                                ->whereHasMorph('reference', [Registration::class], fn (Builder $registrationQuery): Builder => $registrationQuery
                                    ->whereHas('event', fn (Builder $eventQuery): Builder => $eventQuery
                                        ->where('event_type', EventType::Evento->value))),
                            'venta_especial' => $query
                                ->where('reference_type', Registration::class)
                                ->whereHasMorph('reference', [Registration::class], fn (Builder $registrationQuery): Builder => $registrationQuery
                                    ->whereHas('event', fn (Builder $eventQuery): Builder => $eventQuery
                                        ->where('event_type', EventType::Venta->value))),
                            default => $query,
                        };
                    }),

                Filter::make('cuota')
                    ->label('Cuotas')
                    ->form([
                        Select::make('quota_id')
                            ->label('Seleccionar cuota')
                            ->options(fn (): array => Quota::query()
                                ->whereHas('transactions')
                                ->with('member')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(fn (Quota $quota): array => [
                                    $quota->id => sprintf(
                                        'Cuota #%d · Socio #%s · %s',
                                        $quota->id,
                                        $quota->member?->member_number_formatted ?? '-',
                                        $quota->period_start?->format('m/Y') ?? 'Sin periodo',
                                    ),
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['quota_id'] ?? null)
                        ? $query
                            ->where('reference_type', Quota::class)
                            ->where('reference_id', (int) $data['quota_id'])
                        : $query),

                Filter::make('evento')
                    ->label('Evento')
                    ->form([
                        Select::make('event_id')
                            ->label('Seleccionar evento')
                            ->options(fn (): array => Event::query()
                                ->where('event_type', EventType::Evento->value)
                                ->whereHas('registrations.transactions')
                                ->orderByDesc('start_date')
                                ->get()
                                ->mapWithKeys(fn (Event $event): array => [
                                    $event->id => sprintf('%s · %s', $event->event_code ?? 'SIN-CODIGO', $event->name),
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['event_id'] ?? null)
                        ? $query
                            ->where('reference_type', Registration::class)
                            ->whereHasMorph('reference', [Registration::class], fn (Builder $registrationQuery): Builder => $registrationQuery
                                ->where('event_id', (int) $data['event_id'])
                                ->whereHas('event', fn (Builder $eventQuery): Builder => $eventQuery
                                    ->where('event_type', EventType::Evento->value)))
                        : $query),

                Filter::make('venta_especial')
                    ->label('Venta Especial')
                    ->form([
                        Select::make('event_id')
                            ->label('Seleccionar venta especial')
                            ->options(fn (): array => Event::query()
                                ->where('event_type', EventType::Venta->value)
                                ->whereHas('registrations.transactions')
                                ->orderByDesc('start_date')
                                ->get()
                                ->mapWithKeys(fn (Event $event): array => [
                                    $event->id => sprintf('%s · %s', $event->event_code ?? 'SIN-CODIGO', $event->name),
                                ])
                                ->all())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['event_id'] ?? null)
                        ? $query
                            ->where('reference_type', Registration::class)
                            ->whereHasMorph('reference', [Registration::class], fn (Builder $registrationQuery): Builder => $registrationQuery
                                ->where('event_id', (int) $data['event_id'])
                                ->whereHas('event', fn (Builder $eventQuery): Builder => $eventQuery
                                    ->where('event_type', EventType::Venta->value)))
                        : $query),

                Filter::make('importe')
                    ->label('Importe')
                    ->form([
                        Select::make('kind')
                            ->label('Tipo de importe')
                            ->options([
                                'all' => 'Todos',
                                'base' => 'Base',
                                'recargo' => 'Recargo',
                            ])
                            ->default('all'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['kind'] ?? 'all') {
                            'base' => $query->where(function (Builder $baseQuery): Builder {
                                return $baseQuery
                                    ->basePrice()
                                    ->orWhereHas('childTransactions', fn (Builder $childQuery): Builder => $childQuery->basePrice());
                            }),
                            'recargo' => $query->where(function (Builder $surchargeQuery): Builder {
                                return $surchargeQuery
                                    ->surcharge()
                                    ->orWhereHas('childTransactions', fn (Builder $childQuery): Builder => $childQuery->surcharge());
                            }),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver'),
                    EditAction::make()
                        ->label('Editar')
                        ->disabled(fn (Transaction $record): bool => $record->source_type !== TransactionSourceType::Manual),
                ]),
            ])
            ->emptyStateHeading('No hay transacciones registradas.');
    }
}
