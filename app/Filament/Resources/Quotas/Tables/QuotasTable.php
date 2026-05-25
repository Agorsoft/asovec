<?php

namespace App\Filament\Resources\Quotas\Tables;

use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Models\Association;
use App\Models\Quota;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('exportQuotas')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        CheckboxList::make('optional_columns')
                            ->label('Columnas opcionales')
                            ->options([
                                'due_date' => 'Vencimiento',
                                'payment_method' => 'Método de pago',
                                'quota_type_name' => 'Tipo de cuota',
                                'receipt_number' => 'Nº recibo',
                            ])
                            ->columns(2),
                    ])
                    ->modalHeading('Exportar cuotas')
                    ->modalSubmitActionLabel('Descargar CSV')
                    ->action(function (array $data, $livewire): StreamedResponse {
                        $selectedOptionalColumns = collect($data['optional_columns'] ?? [])
                            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                            ->values()
                            ->all();

                        $headers = [
                            'Nº socio',
                            'Socio',
                            'Desde',
                            'Hasta',
                            'Importe',
                            'Estado',
                            'Pago',
                        ];

                        foreach ($selectedOptionalColumns as $column) {
                            $headers[] = match ($column) {
                                'due_date' => 'Vencimiento',
                                'payment_method' => 'Método de pago',
                                'quota_type_name' => 'Tipo de cuota',
                                'receipt_number' => 'Nº recibo',
                                default => $column,
                            };
                        }

                        $quotas = $livewire->getTableQueryForExport()
                            ->with(['member', 'quotaType'])
                            ->get();

                        return response()->streamDownload(function () use ($headers, $quotas, $selectedOptionalColumns): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            // UTF-8 BOM for Excel compatibility with accents.
                            fwrite($stream, "\xEF\xBB\xBF");

                            fputcsv($stream, $headers, ';');

                            /** @var Quota $quota */
                            foreach ($quotas as $quota) {
                                $row = [
                                    $quota->member?->member_number,
                                    trim((string) ($quota->member?->last_name.', '.$quota->member?->first_name)),
                                    $quota->period_start?->format('d/m/Y'),
                                    $quota->period_end?->format('d/m/Y'),
                                    number_format((float) $quota->amount, 2, '.', ''),
                                    $quota->status?->value,
                                    $quota->paid_at?->format('d/m/Y'),
                                ];

                                foreach ($selectedOptionalColumns as $column) {
                                    $row[] = match ($column) {
                                        'due_date' => $quota->due_date?->format('d/m/Y'),
                                        'payment_method' => $quota->payment_method,
                                        'quota_type_name' => $quota->quotaType?->name,
                                        'receipt_number' => $quota->receipt_number,
                                        default => null,
                                    };
                                }

                                fputcsv($stream, $row, ';');
                            }

                            fclose($stream);
                        }, 'cuotas_export_'.now()->format('Ymd_His').'.csv', [
                            'Content-Type' => 'text/csv; charset=utf-8',
                        ]);
                    }),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                QuotaStatus::Pendiente->value,
                QuotaStatus::Pagada->value,
                QuotaStatus::Vencida->value,
            ]))
            ->columns([
                TextColumn::make('member.member_number')
                    ->label('Nº socio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('member.last_name')
                    ->label('Socio')
                    ->state(fn (Quota $record): string => trim("{$record->member?->last_name}, {$record->member?->first_name}"))
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('quotaType.name')
                    ->label('Tipo de cuota')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('period_start')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    // ->state(fn (Quota $record): string => $record->effectiveStatus()->value)
                    ->badge()
                    ->color(fn (QuotaStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Pago')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        QuotaStatus::Pendiente->value => QuotaStatus::Pendiente->Label(),
                        QuotaStatus::Pagada->value => QuotaStatus::Pagada->Label(),
                        QuotaStatus::Vencida->value => QuotaStatus::Vencida->Label(),
                    ]),
                SelectFilter::make('quota_type_id')
                    ->label('Tipo de cuota')
                    ->relationship('quotaType', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('markAsPaid')
                        ->label('Marcar pagada')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Pagada)
                        ->schema([
                            Select::make('payment_method')
                                ->label('Método de pago')
                                ->options(fn (): array => Association::paymentMethodOptions())
                                ->default(fn (): ?string => Association::defaultPaymentMethod())
                                ->native(false)
                                ->required(),
                            Textarea::make('notes')
                                ->label('Notas')
                                ->rows(3),
                        ])
                        ->action(function (Quota $record, array $data): void {
                            $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                                ? $data['payment_method']->value
                                : (string) $data['payment_method'];

                            $record->markAsPaid(
                                $paymentMethod,
                                filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Cuota marcada como pagada')
                                ->actions([
                                    Action::make('printReceipt')
                                        ->label('Imprimir recibo A4')
                                        ->url(route('quotas.receipt.show', ['quota' => $record->id, 'type' => 'full']))
                                        ->openUrlInNewTab()
                                        ->button(),
                                ])
                                ->send();
                        }),
                    Action::make('cancel')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Cancelada)
                        ->requiresConfirmation()
                        ->action(function (Quota $record): void {
                            $wasPaid = $record->status === QuotaStatus::Pagada;

                            $record->update([
                                'status' => QuotaStatus::Cancelada,
                            ]);

                            $notification = Notification::make()
                                ->success()
                                ->title('Cuota cancelada');

                            if ($wasPaid) {
                                $notification->actions([
                                    Action::make('printRefund')
                                        ->label('Imprimir nota de devolución')
                                        ->url(route('quotas.receipt.show', ['quota' => $record->id, 'type' => 'refund']))
                                        ->openUrlInNewTab()
                                        ->button(),
                                ]);
                            }

                            $notification->send();
                        }),
                    ViewAction::make()
                        ->label('Ver'),
                    EditAction::make()
                        ->label('Editar'),
                    Action::make('separator')
                        ->label('—')
                        ->disabled()
                        ->color('gray'),
                    Action::make('printRefundReceipt')
                        ->label('Nota de devolución')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => $record->status === QuotaStatus::Cancelada)
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'refund',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptFull')
                        ->label('Recibo completo')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptMember')
                        ->label('Recibo de socio')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'member',
                        ]))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }
}
