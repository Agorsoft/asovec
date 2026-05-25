<?php

namespace App\Filament\Resources\DocumentTemplates\Pages;

use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Services\Documents\DocxPreviewGenerator;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDocumentTemplate extends ViewRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected static ?string $title = 'Ver plantilla de documento';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => EventResource::getUrl('index'))
                ->color('gray'),
            Action::make('preview')
                ->label('Vista previa')
                ->icon(Heroicon::Eye)
                ->modalHeading('Vista previa del documento')
                ->modalContent(fn ($record) => view(
                    'filament.modals.document-preview',
                    ['html' => app(DocxPreviewGenerator::class)->getOrGenerate($record) ?? '<p class="text-gray-400 italic">No se pudo generar la vista previa.</p>'],
                ))
                ->modalWidth('4xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            EditAction::make()
                ->label('Editar'),
        ];
    }
}
