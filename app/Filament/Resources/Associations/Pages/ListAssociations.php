<?php

namespace App\Filament\Resources\Associations\Pages;

use App\Filament\Resources\Associations\AssociationResource;
use App\Models\Association;
use Filament\Resources\Pages\ListRecords;

class ListAssociations extends ListRecords
{
    protected static string $resource = AssociationResource::class;

    public function mount(): void
    {
        parent::mount();

        $association = Association::query()->first();

        if (! $association) {
            $this->redirect(AssociationResource::getUrl('setup'));

            return;
        }

        $this->redirect(AssociationResource::getUrl('view', ['record' => $association]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
