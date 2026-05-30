<?php

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Resources\Associations\AssociationResource;
use Filament\Resources\Pages\EditRecord;

class EditAssociation extends EditRecord
{
    protected static string $resource = AssociationResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? $this->getResourceUrl();
    }
}
