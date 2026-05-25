<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Enums\PaymentMethod;
use App\Models\Registration;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Association;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected static ?string $title = 'Editar inscripción';

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? $this->getResourceUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => RegistrationResource::getUrl('index'))
                ->color('gray'),
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
            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelada)
                ->requiresConfirmation()
                ->action(function (Registration $record): void {
                    $wasConfirmed = $record->status === RegistrationStatus::Confirmada;

                    $record->update([
                        'status' => RegistrationStatus::Cancelada,
                    ]);

                    $notification = Notification::make()
                        ->success()
                        ->title('Inscripción cancelada');

                    if ($wasConfirmed) {
                        $notification->actions([
                            Action::make('printRefund')
                                ->label('Imprimir nota de devolución')
                                ->url(route('registrations.receipt.show', ['registration' => $record->id, 'type' => 'refund']))
                                ->openUrlInNewTab()
                                ->button(),
                        ]);
                    }

                    $notification->send();
                }),

        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $type = $data['registration_type'] ?? null;

        if ($type instanceof RegistrationType) {
            $type = $type->value;
        }

        if ($type !== RegistrationType::Acompanante->value) {
            return $data;
        }

        // Model stores member_id=companion and sponsor_member_id=sponsor.
        // UI expects member_id=sponsor and sponsor_member_id=companion.
        $companionId = $data['member_id'] ?? null;
        $sponsorId = $data['sponsor_member_id'] ?? null;

        $data['member_id'] = $sponsorId;
        $data['sponsor_member_id'] = $companionId;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $type = $data['registration_type'] ?? null;

        if ($type instanceof RegistrationType) {
            $type = $type->value;
        }

        if ($type !== RegistrationType::Acompanante->value) {
            return $data;
        }

        $sponsorId = $data['member_id'] ?? null;
        $companionId = $data['sponsor_member_id'] ?? null;

        $data['member_id'] = $companionId;
        $data['sponsor_member_id'] = $sponsorId;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return parent::handleRecordUpdate($record, $data);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            Notification::make()
                ->danger()
                ->title('No se puede guardar la inscripción')
                ->body((string) $message)
                ->send();
        }
    }
}
