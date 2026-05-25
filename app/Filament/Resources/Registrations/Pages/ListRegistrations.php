<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistrations extends ListRecords
{
    protected static string $resource = RegistrationResource::class;

    protected static ?string $title = 'Inscripciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva inscripción'),
        ];
    }
}
