<?php

namespace App\Filament\Resources\DocumentTemplates;

use App\Filament\Clusters\Plantillas\PlantillasCluster;
use App\Filament\Resources\DocumentTemplates\Pages\CreateDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\EditDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Pages\ListDocumentTemplates;
use App\Filament\Resources\DocumentTemplates\Pages\ViewDocumentTemplate;
use App\Filament\Resources\DocumentTemplates\Schemas\DocumentTemplateForm;
use App\Filament\Resources\DocumentTemplates\Schemas\DocumentTemplateInfolist;
use App\Filament\Resources\DocumentTemplates\Tables\DocumentTemplatesTable;
use App\Models\DocumentTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplateResource extends Resource
{
    protected static ?string $model = DocumentTemplate::class;

    protected static ?string $cluster = PlantillasCluster::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $modelLabel = 'Plantilla de documento';

    protected static ?string $pluralModelLabel = 'Plantillas de documentos';

    protected static ?string $navigationLabel = 'Plantillas de documentos';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return DocumentTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentTemplates::route('/'),
            'create' => CreateDocumentTemplate::route('/create'),
            'view' => ViewDocumentTemplate::route('/{record}'),
            'edit' => EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::current()?->hasPermission('documents.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return User::current()?->hasPermission('documents.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return User::current()?->hasPermission('documents.edit') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return User::current()?->hasPermission('documents.edit') ?? false;
    }
}
