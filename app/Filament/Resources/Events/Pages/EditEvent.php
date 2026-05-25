<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Editar evento';

    protected function getAllRelationManagers(): array
    {
        return [];
    }

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
                ->url(fn () => EventResource::getUrl('index'))
                ->color('gray'),
            DeleteAction::make()
                ->label('Eliminar')
                ->visible(fn (): bool => ! $this->getRecord()->registrations()->exists()),
        ];
    }
}
