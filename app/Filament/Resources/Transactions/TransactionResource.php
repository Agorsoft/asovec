<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionSourceType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Pages\ViewTransaction;
use App\Filament\Resources\Transactions\Schemas\TransactionForm;
use App\Filament\Resources\Transactions\Schemas\TransactionInfolist;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Transaction;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static bool $hasTitleCaseModelLabel = false;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $modelLabel = 'Transacción';

    protected static ?string $pluralModelLabel = 'Transacciones';

    protected static ?string $navigationLabel = 'Transacciones';

    protected static string|UnitEnum|null $navigationGroup = 'Contabilidad';

    public static function form(Schema $schema): Schema
    {
        return TransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
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
            'index' => ListTransactions::route('/'),
            'create' => CreateTransaction::route('/create'),
            'view' => ViewTransaction::route('/{record}'),
            'edit' => EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::current()?->hasPermission('transactions.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return User::current()?->hasPermission('transactions.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return (User::current()?->hasPermission('transactions.edit') ?? false)
            && $record->source_type === TransactionSourceType::Manual;
    }

    public static function canDelete(Model $record): bool
    {
        return (User::current()?->is_admin ?? false)
            && $record->source_type === TransactionSourceType::Manual;
    }
}
