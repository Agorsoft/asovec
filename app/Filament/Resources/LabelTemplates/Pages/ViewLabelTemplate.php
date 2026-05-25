<?php

namespace App\Filament\Resources\LabelTemplates\Pages;

use App\Filament\Resources\LabelTemplates\LabelTemplateResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewLabelTemplate extends ViewRecord
{
    protected static string $resource = LabelTemplateResource::class;

    protected static ?string $title = 'Ver plantilla de etiquetas';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => LabelTemplateResource::getUrl('index'))
                ->color('gray'),
            EditAction::make()
                ->label('Editar'),
        ];
    }
}
