<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Enums\PaymentMethod;
use App\Enums\RegistrationStatus;
use App\Models\Association;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                RegistrationStatus::Pendiente->value,
                RegistrationStatus::Confirmada->value,
            ]))
            ->columns([
                TextColumn::make('registration_number')
                    ->label('Nº inscripción')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event.name')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('member.last_name')
                    ->label('Participante')
                    ->state(fn (Registration $record): string => $record->member?->full_name ?? '—')
                    ->searchable(['member.first_name', 'member.last_name']),
                TextColumn::make('registration_type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (RegistrationStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('surcharge_amount')
                    ->label('Recargo')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Pagada')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        RegistrationStatus::Pendiente->value => RegistrationStatus::Pendiente->getLabel(),
                        RegistrationStatus::Confirmada->value => RegistrationStatus::Confirmada->getLabel(),
                    ]),
                SelectFilter::make('event_id')
                    ->label('Evento')
                    ->relationship('event', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('confirm')
                        ->label('Confirmar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::Pendiente)
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
                        ->action(function (Registration $record, array $data): void {
                            $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                                ? $data['payment_method']->value
                                : (string) $data['payment_method'];

                            $record->confirm(
                                $paymentMethod,
                                filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Inscripción confirmada')
                                ->actions([
                                    Action::make('printReceipt')
                                        ->label('Imprimir recibo A4')
                                        ->url(route('registrations.receipt.show', ['registration' => $record->id, 'type' => 'full']))
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
                        ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record) => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'refund',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptFull')
                        ->label('Recibo completo')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->url(fn (Registration $record) => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->url(fn (Registration $record) => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptMember')
                        ->label('Recibo de socio')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->url(fn (Registration $record) => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'member',
                        ]))
                        ->openUrlInNewTab(),
                ]),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No hay inscripciones registradas.');
    }
}
