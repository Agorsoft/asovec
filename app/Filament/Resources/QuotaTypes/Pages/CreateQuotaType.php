<?php

namespace App\Filament\Resources\QuotaTypes\Pages;

use App\Filament\Resources\QuotaTypes\QuotaTypeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateQuotaType extends CreateRecord
{
    protected static string $resource = QuotaTypeResource::class;

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }
}
