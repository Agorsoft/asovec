<?php

namespace App\Filament\Resources\QuotaTypes;

use App\Filament\Resources\QuotaTypes\Pages\CreateQuotaType;
use App\Filament\Resources\QuotaTypes\Pages\EditQuotaType;
use App\Filament\Resources\QuotaTypes\Pages\ListQuotaTypes;
use App\Filament\Resources\QuotaTypes\Pages\ViewQuotaType;
use App\Filament\Resources\QuotaTypes\Schemas\QuotaTypeForm;
use App\Filament\Resources\QuotaTypes\Schemas\QuotaTypeInfolist;
use App\Filament\Resources\QuotaTypes\Tables\QuotaTypesTable;
use App\Models\QuotaType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class QuotaTypeResource extends Resource
{
    protected static ?string $model = QuotaType::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $modelLabel = 'Tipo de cuota';

    protected static ?string $pluralModelLabel = 'Tipos de cuota';

    protected static ?string $navigationLabel = 'Tipos de cuota';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return QuotaTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuotaTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotaTypesTable::configure($table);
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
            'index' => ListQuotaTypes::route('/'),
            'create' => CreateQuotaType::route('/create'),
            'view' => ViewQuotaType::route('/{record}'),
            'edit' => EditQuotaType::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->is_admin && ! $record->quotas()->exists();
    }
}
