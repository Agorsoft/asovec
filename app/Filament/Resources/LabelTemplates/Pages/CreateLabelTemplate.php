<?php

namespace App\Filament\Resources\LabelTemplates\Pages;

use App\Filament\Resources\LabelTemplates\LabelTemplateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateLabelTemplate extends CreateRecord
{
    protected static string $resource = LabelTemplateResource::class;

    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()->hidden();
    }
}
