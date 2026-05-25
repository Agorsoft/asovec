<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Enums\RegistrationType;
use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRegistration extends CreateRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['registration_type'] ?? null;

        if ($type instanceof RegistrationType) {
            $type = $type->value;
        }

        if ($type !== RegistrationType::Acompanante->value) {
            return $data;
        }

        // UI uses member_id as sponsor and sponsor_member_id as companion.
        // Persist using model semantics: member_id=companion, sponsor_member_id=sponsor.
        $sponsorId = $data['member_id'] ?? null;
        $companionId = $data['sponsor_member_id'] ?? null;

        $data['member_id'] = $companionId;
        $data['sponsor_member_id'] = $sponsorId;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return parent::handleRecordCreation($data);
    }

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();

            Notification::make()
                ->danger()
                ->title('No se puede guardar la inscripción')
                ->body((string) $message)
                ->send();
        }
    }

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }

    protected static ?string $title = 'Nueva inscripción';
}
