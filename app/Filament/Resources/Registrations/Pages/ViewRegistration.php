<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Association;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistration extends ViewRecord
{
    protected static string $resource = RegistrationResource::class;

    protected static ?string $title = 'Ver inscripción';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => RegistrationResource::getUrl('index'))
                ->color('gray'),
            ActionGroup::make([
                Action::make('printReceiptFull')
                    ->label('Recibo completo')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== RegistrationStatus::Cancelada)
                    ->url(fn () => route('registrations.receipt.show', [
                        'registration' => $this->record->id,
                        'type' => 'full',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptAssociation')
                    ->label('Recibo de asociación')
                    ->icon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== RegistrationStatus::Cancelada)
                    ->url(fn () => route('registrations.receipt.show', [
                        'registration' => $this->record->id,
                        'type' => 'association',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptMember')
                    ->label('Recibo de participante')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->status !== RegistrationStatus::Cancelada)
                    ->url(fn () => route('registrations.receipt.show', [
                        'registration' => $this->record->id,
                        'type' => 'member',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printRefundReceipt')
                    ->label('Nota de devolución')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->status === RegistrationStatus::Cancelada)
                    ->url(fn () => route('registrations.receipt.show', [
                        'registration' => $this->record->id,
                        'type' => 'refund',
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->button(),
            Action::make('confirm')
                ->label('Confirmar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === RegistrationStatus::Pendiente)
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

                    $this->record->confirm(
                        $paymentMethod,
                        filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Inscripción confirmada')
                        ->actions([
                            Action::make('printReceipt')
                                ->label('Imprimir recibo A4')
                                ->url(route('registrations.receipt.show', ['registration' => $this->record->id, 'type' => 'full']))
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
                ->visible(fn (): bool => $this->record->status !== RegistrationStatus::Cancelada)
                ->requiresConfirmation()
                ->action(function (): void {
                    $wasConfirmed = $this->record->status === RegistrationStatus::Confirmada;

                    $this->record->update([
                        'status' => RegistrationStatus::Cancelada,
                    ]);

                    $this->record->refresh();

                    $notification = Notification::make()
                        ->success()
                        ->title('Inscripción cancelada');

                    if ($wasConfirmed) {
                        $notification->actions([
                            Action::make('printRefund')
                                ->label('Imprimir nota de devolución')
                                ->url(route('registrations.receipt.show', ['registration' => $this->record->id, 'type' => 'refund']))
                                ->openUrlInNewTab()
                                ->button(),
                        ]);
                    }

                    $notification->send();
                }),
        ];
    }
}
