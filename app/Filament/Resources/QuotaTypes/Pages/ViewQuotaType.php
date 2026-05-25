<?php

namespace App\Filament\Resources\QuotaTypes\Pages;

use App\Filament\Resources\QuotaTypes\QuotaTypeResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotaType extends ViewRecord
{
    protected static string $resource = QuotaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => QuotaTypeResource::getUrl('index'))
                ->color('gray'),
            EditAction::make(),
        ];
    }
}
