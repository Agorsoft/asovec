<?php

namespace App\Filament\Resources\QuotaTypes\Pages;

use App\Filament\Resources\QuotaTypes\QuotaTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQuotaTypes extends ListRecords
{
    protected static string $resource = QuotaTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
