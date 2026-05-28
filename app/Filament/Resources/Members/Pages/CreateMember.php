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
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (Arr::get($data, 'status') === MemberStatus::Activo->value && empty(Arr::get($data, 'joined_at'))) {
            $data['joined_at'] = Carbon::now()->toDateString();
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

    protected function afterCreate(): void
    {
        // Sync last_member_number in Association to track the highest used number
        $memberNumber = (int) $this->record->member_number;
        if ($memberNumber > 0) {
            $association = Association::first();
            if (! $association) {
                // Create minimal association row if missing to persist last_member_number
                Association::create(['last_member_number' => $memberNumber]);
            } elseif ($memberNumber > $association->last_member_number) {
                $association->update(['last_member_number' => $memberNumber]);
            }
        }

        $state = $this->form->getState();

        // Ask about printing the consent sheet before any early returns from quota logic.
        $this->maybeNotifyPrintConsent();

        if (($state['status'] ?? null) === MemberStatus::Activo->value && ! empty($state['quota_id'])) {
            $quotaType = QuotaTypeResolver::resolve((int) $state['quota_id']);

            if (! $quotaType) {
                return;
            }

            $periodData = QuotaPeriodService::forMember($this->record, $quotaType);

            if (Quota::existsForMemberPeriod(
                $this->record->id,
                $periodData['period_start'],
                $periodData['period_end'],
            )) {
                return;
            }

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

        if (($state['status'] ?? null) === MemberStatus::Pendiente->value && ! empty($state['quota_id'])) {
            $quotaType = QuotaTypeResolver::resolve((int) $state['quota_id']);

            if (! $quotaType) {
                return;
            }

            $periodData = QuotaPeriodService::forMember($this->record, $quotaType);

            if (Quota::existsForMemberPeriod(
                $this->record->id,
                $periodData['period_start'],
                $periodData['period_end'],
            )) {
                return;
            }

            Quota::query()->create([
                'member_id' => $this->record->id,
                'quota_type_id' => $quotaType->id,
                'period_start' => $periodData['period_start'],
                'period_end' => $periodData['period_end'],
                'due_date' => $periodData['due_date'],
                'amount' => QuotaPeriodService::defaultAmount($quotaType) ?? 0,
                'status' => QuotaStatus::Pendiente,
            ]);
        }
    }

    private function maybeNotifyPrintConsent(): void
    {
        if (! $this->record->whatsapp_opt_in && ! $this->record->sms_opt_in && ! $this->record->email_opt_in) {
            return;
        }

        Notification::make()
            ->info()
            ->title('¿Imprimir hoja de consentimiento?')
            ->body('El socio tiene preferencias de comunicación marcadas. ¿Deseas imprimir la hoja de consentimiento para que la firme?')
            ->persistent()
            ->actions([
                Action::make('print')
                    ->label('Imprimir')
                    ->button()
                    ->url(route('members.consent.print', $this->record), shouldOpenInNewTab: true),
                Action::make('skip')
                    ->label('Omitir')
                    ->close(),
            ])
            ->send();
    }
}
