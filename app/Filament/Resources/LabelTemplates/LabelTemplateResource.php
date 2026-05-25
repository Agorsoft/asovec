<?php

namespace App\Filament\Resources\LabelTemplates;

use App\Filament\Resources\LabelTemplates\Pages\CreateLabelTemplate;
use App\Filament\Resources\LabelTemplates\Pages\EditLabelTemplate;
use App\Filament\Resources\LabelTemplates\Pages\ListLabelTemplates;
use App\Filament\Resources\LabelTemplates\Pages\ViewLabelTemplate;
use App\Filament\Resources\LabelTemplates\Schemas\LabelTemplateForm;
use App\Filament\Resources\LabelTemplates\Schemas\LabelTemplateInfolist;
use App\Filament\Resources\LabelTemplates\Tables\LabelTemplatesTable;
use App\Models\LabelTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LabelTemplateResource extends Resource
{
    protected static ?string $model = LabelTemplate::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;
    protected static ?string $modelLabel = 'Plantilla de etiquetas';

    protected static ?string $pluralModelLabel = 'Plantillas de etiquetas';

    protected static ?string $navigationLabel = 'Plantillas de etiquetas';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return LabelTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LabelTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LabelTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLabelTemplates::route('/'),
            'create' => CreateLabelTemplate::route('/create'),
            'view' => ViewLabelTemplate::route('/{record}'),
            'edit' => EditLabelTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('labels.manage') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasPermission('labels.manage') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasPermission('labels.manage') ?? false;
    }
}
