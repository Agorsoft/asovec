<?php

namespace App\Filament\Resources\Quotas;

use App\Filament\Resources\Quotas\Pages\CreateQuota;
use App\Filament\Resources\Quotas\Pages\EditQuota;
use App\Filament\Resources\Quotas\Pages\ListQuotas;
use App\Filament\Resources\Quotas\Pages\ViewQuota;
use App\Filament\Resources\Quotas\Schemas\QuotaForm;
use App\Filament\Resources\Quotas\Schemas\QuotaInfolist;
use App\Filament\Resources\Quotas\Tables\QuotasTable;
use App\Models\Quota;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class QuotaResource extends Resource
{
    protected static ?string $model = Quota::class;

    protected static bool $hasTitleCaseModelLabel = false;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $modelLabel = 'Cuota';

    protected static ?string $pluralModelLabel = 'Cuotas';

    protected static ?string $navigationLabel = 'Cuotas';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión de Socios';

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return QuotaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuotaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotasTable::configure($table);
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
            'index' => ListQuotas::route('/'),
            'create' => CreateQuota::route('/create'),
            'view' => ViewQuota::route('/{record}'),
            'edit' => EditQuota::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('quotas.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('quotas.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasPermission('quotas.edit') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
