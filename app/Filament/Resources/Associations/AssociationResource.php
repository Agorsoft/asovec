<?php

namespace App\Filament\Resources\Associations;

use App\Filament\Resources\Associations\Pages\EditAssociation;
use App\Filament\Resources\Associations\Pages\ListAssociations;
use App\Filament\Resources\Associations\Pages\SetupAssociation;
use App\Filament\Resources\Associations\Pages\ViewAssociation;
use App\Filament\Resources\Associations\Schemas\AssociationForm;
use App\Filament\Resources\Associations\Schemas\AssociationInfolist;
use App\Filament\Resources\Associations\Tables\AssociationsTable;
use App\Models\Association;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AssociationResource extends Resource
{
    protected static ?string $model = Association::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;
    protected static ?string $modelLabel = 'Asociación';

    protected static ?string $pluralModelLabel = 'Asociación';

    protected static ?string $navigationLabel = 'Asociación';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AssociationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssociationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssociationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssociations::route('/'),
            'setup' => SetupAssociation::route('/setup'),
            'view' => ViewAssociation::route('/{record}'),
            'edit' => EditAssociation::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_admin && ! Association::query()->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
