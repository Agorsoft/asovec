<?php

namespace App\Filament\Resources\Quotas\Pages;

use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Filament\Resources\Quotas\QuotaResource;
use App\Models\Association;
use App\Models\Quota;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewQuota extends ViewRecord
{
    protected static string $resource = QuotaResource::class;

    protected static ?string $title = 'Ver cuota';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => QuotaResource::getUrl('index'))
                ->color('gray'),
            ActionGroup::make([
                Action::make('printReceiptFull')
                    ->label('Recibo completo')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== QuotaStatus::Cancelada)
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'full',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptAssociation')
                    ->label('Recibo de asociación')
                    ->icon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== QuotaStatus::Cancelada)
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'association',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptMember')
                    ->label('Recibo de socio')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== QuotaStatus::Cancelada)
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'member',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printRefundReceipt')
                    ->label('Nota de devolución')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->status === QuotaStatus::Cancelada)
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'refund',
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Imprimir recibo')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->button(),
            Action::make('markAsPaid')
                ->label('Marcar pagada')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== QuotaStatus::Pagada)
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
                ->action(function (array $data): void {
                    $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                        ? $data['payment_method']->value
                        : (string) $data['payment_method'];

                    $this->record->markAsPaid(
                        $paymentMethod,
                        filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Cuota marcada como pagada')
                        ->actions([
                            Action::make('printReceipt')
                                ->label('Imprimir recibo A4')
                                ->url(route('quotas.receipt.show', ['quota' => $this->record->id, 'type' => 'full']))
                                ->openUrlInNewTab()
                                ->button(),
                        ])
                        ->send();
                }),
            EditAction::make()
                ->label('Editar'),
            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status !== QuotaStatus::Cancelada
                    && $this->record->canBeCancelled())
                ->requiresConfirmation()
                ->action(function (): void {
                    $wasPaid = $this->record->status === QuotaStatus::Pagada;

                    $this->record->update([
                        'status' => QuotaStatus::Cancelada,
                    ]);

                    $this->record->refresh();

                    $notification = Notification::make()
                        ->success()
                        ->title('Cuota cancelada');

                    if ($wasPaid) {
                        $notification->actions([
                            Action::make('printRefund')
                                ->label('Imprimir nota de devolución')
                                ->url(route('quotas.receipt.show', ['quota' => $this->record->id, 'type' => 'refund']))
                                ->openUrlInNewTab()
                                ->button(),
                        ]);
                    }

                    $notification->send();
                }),
        ];
    }
}
