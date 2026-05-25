<?php

namespace App\Filament\Resources\Quotas\Pages;

use App\Enums\QuotaStatus;
use App\Filament\Resources\Quotas\QuotaResource;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Services\QuotaPeriodService;
use App\Services\QuotaTypeResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateQuota extends CreateRecord
{
    protected static string $resource = QuotaResource::class;

    protected ?string $paymentMethodOnCreate = null;

    protected ?string $paymentNotesOnCreate = null;

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = $data['status'] ?? null;

        if ($status instanceof QuotaStatus) {
            $status = $status->value;
        }

        $data['status'] = is_string($status) ? $status : QuotaStatus::Pendiente->value;

        $quotaType = $this->resolveQuotaType($data);

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

        $this->ensureUniquePeriod($data);

        if ($data['status'] === QuotaStatus::Pagada->value) {
            if (! filled($data['payment_method'] ?? null)) {
                throw ValidationException::withMessages([
                    'member_id' => 'El socio no tiene método de pago configurado para registrar la cuota como pagada.',
                ]);
            }

            $this->paymentMethodOnCreate = (string) $data['payment_method'];
            $this->paymentNotesOnCreate = isset($data['notes']) ? (string) $data['notes'] : null;
            $data['status'] = QuotaStatus::Pendiente->value;
            $data['paid_at'] = null;
            $data['receipt_number'] = null;
            $data['due_date'] = $data['due_date'] ?? now()->toDateString();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->record instanceof Quota || ! filled($this->paymentMethodOnCreate)) {
            return;
        }

        $this->record->markAsPaid($this->paymentMethodOnCreate, $this->paymentNotesOnCreate);

        Notification::make()
            ->success()
            ->title('Cuota registrada y cobrada')
            ->send();
    }

    protected function resolveQuotaType(array &$data): QuotaType
    {
        $selectedQuotaTypeId = filled($data['quota_type_id'] ?? null) ? (int) $data['quota_type_id'] : null;

        if (! QuotaTypeResolver::hasAvailableTypes($selectedQuotaTypeId)) {
            throw ValidationException::withMessages([
                'quota_type_id' => 'No hay tipos de cuota vigentes para el periodo por defecto.',
            ]);
        }

        $resolvedQuotaTypeId = QuotaTypeResolver::defaultQuotaTypeId($selectedQuotaTypeId);

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

    protected function ensureUniquePeriod(array $data): void
    {
        if (! filled($data['member_id'] ?? null) || ! filled($data['period_start'] ?? null) || ! filled($data['period_end'] ?? null)) {
            return;
        }

        if (Quota::existsForMemberPeriod((int) $data['member_id'], (string) $data['period_start'], (string) $data['period_end'])) {
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
