<?php

namespace App\Filament\Resources\QuotaTypes\Pages;

use App\Filament\Resources\QuotaTypes\QuotaTypeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQuotaType extends EditRecord
{
    protected static string $resource = QuotaTypeResource::class;

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
                ->url(fn () => QuotaTypeResource::getUrl('index'))
                ->color('gray'),
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->getRecord()->quotas()->exists()),
        ];
    }
}
