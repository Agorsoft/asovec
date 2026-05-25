<?php

namespace App\Filament\Resources\Quotas\Pages;

use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Filament\Resources\Quotas\QuotaResource;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Services\QuotaPeriodService;
use App\Services\QuotaTypeResolver;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditQuota extends EditRecord
{
    protected static string $resource = QuotaResource::class;

    protected static ?string $title = 'Editar cuota';

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
                ->url(fn () => QuotaResource::getUrl('index'))
                ->color('gray'),
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

    protected ?string $paymentMethodOnUpdate = null;

    protected ?string $paymentNotesOnUpdate = null;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Quota) {
            return parent::handleRecordUpdate($record, $data);
        }

        $status = $data['status'] ?? null;

        if ($status instanceof QuotaStatus) {
            $status = $status->value;
        }

        $data['status'] = is_string($status) ? $status : QuotaStatus::Pendiente->value;
        $quotaType = $this->resolveQuotaType($data, $record);

        $member = Member::query()->find($data['member_id'] ?? null);

        if ($member) {
            $paymentMethodId = $member->payment_method_id;
            $data['payment_method'] = $paymentMethodId instanceof \BackedEnum
                ? $paymentMethodId->value
                : (filled($paymentMethodId) ? (string) $paymentMethodId : null);
            $dates = QuotaPeriodService::forMember($member, $quotaType);

            if (! filled($data['period_start'] ?? null) || ! filled($data['period_end'] ?? null)) {
                $data['period_start'] = $dates['period_start'];
                $data['period_end'] = $dates['period_end'];
            }

            $data['due_date'] = $data['due_date'] ?? $dates['due_date'];

            if (! filled($data['amount'] ?? null) && QuotaPeriodService::defaultAmount($quotaType) !== null) {
                $data['amount'] = QuotaPeriodService::defaultAmount($quotaType);
            }
        }

        $this->ensureUniquePeriod($record, $data);

        $wantsPaid = $data['status'] === QuotaStatus::Pagada->value;
        $isAlreadyPaid = $record->status === QuotaStatus::Pagada;

        if ($wantsPaid && ! $isAlreadyPaid) {
            if (! filled($data['payment_method'] ?? null)) {
                throw ValidationException::withMessages([
                    'member_id' => 'El socio no tiene método de pago configurado para registrar la cuota como pagada.',
                ]);
            }

            $this->paymentMethodOnUpdate = (string) $data['payment_method'];
            $this->paymentNotesOnUpdate = isset($data['notes']) ? (string) $data['notes'] : null;
            $data['status'] = QuotaStatus::Pendiente->value;
            $data['paid_at'] = null;
            $data['receipt_number'] = null;
            $data['due_date'] = $data['due_date'] ?? now()->toDateString();
        }

        $record->update($data);

        if (filled($this->paymentMethodOnUpdate)) {
            $record->markAsPaid($this->paymentMethodOnUpdate, $this->paymentNotesOnUpdate);

            Notification::make()
                ->success()
                ->title('Cuota cobrada')
                ->send();
        }

        return $record;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function resolveQuotaType(array &$data, Quota $record): QuotaType
    {
        $selectedQuotaTypeId = filled($data['quota_type_id'] ?? null) ? (int) $data['quota_type_id'] : null;
        $currentQuotaTypeId = $selectedQuotaTypeId ?: $record->quota_type_id;

        if (! QuotaTypeResolver::hasAvailableTypes($currentQuotaTypeId)) {
            throw ValidationException::withMessages([
                'quota_type_id' => 'No hay tipos de cuota vigentes para el periodo por defecto.',
            ]);
        }

        $resolvedQuotaTypeId = QuotaTypeResolver::defaultQuotaTypeId($currentQuotaTypeId);

        if (! $resolvedQuotaTypeId) {
            throw ValidationException::withMessages([
                'quota_type_id' => 'Selecciona un tipo de cuota.',
            ]);
        }

        $quotaType = QuotaTypeResolver::resolve($resolvedQuotaTypeId);

        if (! $quotaType instanceof QuotaType) {
            throw ValidationException::withMessages([
                'quota_type_id' => 'El tipo de cuota seleccionado no es válido.',
            ]);
        }

        $data['quota_type_id'] = $quotaType->id;

        return $quotaType;
    }

    protected function ensureUniquePeriod(Quota $record, array $data): void
    {
        if (! filled($data['member_id'] ?? null) || ! filled($data['period_start'] ?? null) || ! filled($data['period_end'] ?? null)) {
            return;
        }

        if (Quota::existsForMemberPeriod((int) $data['member_id'], (string) $data['period_start'], (string) $data['period_end'], $record->id)) {
            Notification::make()
                ->title('Cuota duplicada')
                ->body('Ya existe una cuota para este socio en el periodo indicado.')
                ->danger()
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'member_id' => 'Ya existe una cuota para este socio en el periodo indicado.',
                'period_start' => 'Ya existe una cuota para este socio en el periodo indicado.',
                'period_end' => 'Ya existe una cuota para este socio en el periodo indicado.',
                'data.member_id' => 'Ya existe una cuota para este socio en el periodo indicado.',
                'data.period_start' => 'Ya existe una cuota para este socio en el periodo indicado.',
                'data.period_end' => 'Ya existe una cuota para este socio en el periodo indicado.',
            ]);
        }
    }
}
