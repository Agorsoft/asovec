<?php

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Resources\Associations\AssociationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditAssociation extends EditRecord
{
    protected static string $resource = AssociationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => AssociationResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? $this->getResourceUrl();
    }
}
