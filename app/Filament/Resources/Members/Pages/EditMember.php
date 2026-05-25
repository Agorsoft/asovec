<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Enums\QuotaStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Association;
use App\Models\Quota;
use App\Services\QuotaPeriodService;
use App\Services\QuotaTypeResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getAllRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => MemberResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? $this->getResourceUrl();
    }

    protected function onValidationError(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Errores en el formulario')
            ->body(collect($exception->errors())->flatten()->join("\n"))
            ->persistent()
            ->send();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (Arr::get($data, 'status') === MemberStatus::Activo->value && empty(Arr::get($data, 'joined_at'))) {
            $data['joined_at'] = Carbon::now()->toDateString();
        }

        if (
            Arr::get($data, 'status') === MemberStatus::Pendiente->value
            && $this->record->status === MemberStatus::Activo
            && $this->record->hasCurrentPaidQuota()
        ) {
            throw ValidationException::withMessages([
                'status' => 'No se puede cambiar a Pendiente mientras exista una cuota actual pagada. Solo puede pasar a Baja.',
            ]);
        }

        // If status is Baja and no left_at provided, set to today
        if (Arr::get($data, 'status') === MemberStatus::Baja->value && empty(Arr::get($data, 'left_at'))) {
            $data['left_at'] = Carbon::now()->toDateString();
        }

        // If trying to activate but no quota selected, revert to Pendiente and notify
        if (Arr::get($data, 'status') === MemberStatus::Activo->value && empty(Arr::get($data, 'quota_id'))) {
            $data['status'] = MemberStatus::Pendiente->value;

            $hasAvailableTypes = QuotaTypeResolver::hasAvailableTypes();

            Notification::make()
                ->warning()
                ->title($hasAvailableTypes ? 'No se asignó cuota' : 'No hay tipos de cuota disponibles')
                ->body($hasAvailableTypes
                    ? 'No se seleccionó ningún tipo de cuota. El socio se guardará como Pendiente.'
                    : 'No existen tipos de cuota para activar socios. Cree al menos uno y vuelva a intentarlo.')
                ->send();
        }

        if (Arr::get($data, 'status') === MemberStatus::Activo->value && empty(Arr::get($data, 'member_number'))) {
            $data['member_number'] = (string) Association::nextMemberNumber();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();

        if (($state['status'] ?? null) === MemberStatus::Activo->value && ! empty($state['quota_id'])) {
            $quotaType = QuotaTypeResolver::resolve((int) $state['quota_id']);

            if ($quotaType) {
                $periodData = QuotaPeriodService::forMember($this->record, $quotaType);

                $paymentMethod = $this->record->payment_method_id;
                $paymentMethodValue = $paymentMethod instanceof \BackedEnum
                    ? $paymentMethod->value
                    : (is_string($paymentMethod) ? $paymentMethod : null);

                $quota = Quota::query()->where('member_id', $this->record->id)
                    ->whereDate('period_start', $periodData['period_start'])
                    ->whereDate('period_end', $periodData['period_end'])
                    ->where('status', '!=', QuotaStatus::Cancelada->value)
                    ->first();

                if ($quota) {
                    if ($quota->status !== QuotaStatus::Pagada) {
                        $quota->markAsPaid($paymentMethodValue ?? 'Transferencia');
                    }

                    return;
                }

                Quota::query()->create([
                    'member_id' => $this->record->id,
                    'quota_type_id' => $quotaType->id,
                    'period_start' => $periodData['period_start'],
                    'period_end' => $periodData['period_end'],
                    'due_date' => $periodData['due_date'],
                    'amount' => QuotaPeriodService::defaultAmount($quotaType) ?? 0,
                    'status' => QuotaStatus::Pagada,
                    'paid_at' => now(),
                    'payment_method' => $paymentMethodValue,
                ]);
            }
        }

        // Sync last_member_number if this record's number is greater
        $memberNumber = (int) $this->record->member_number;
        if ($memberNumber > 0) {
            $association = Association::first();
            if (! $association) {
                Association::create(['last_member_number' => $memberNumber]);
            } elseif ($memberNumber > $association->last_member_number) {
                $association->update(['last_member_number' => $memberNumber]);
            }
        }
    }
}
