<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\EditNonMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\Pages\ViewNonMember;
use App\Filament\Resources\Members\RelationManagers\QuotasRelationManager;
use App\Filament\Resources\Members\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Members\Schemas\MemberForm;
use App\Filament\Resources\Members\Schemas\MemberInfolist;
use App\Filament\Resources\Members\Tables\MembersTable;
use App\Models\Member;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;
    protected static ?string $modelLabel = 'Socio';

    protected static ?string $pluralModelLabel = 'Socios';

    protected static ?string $navigationLabel = 'Socios';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión de Socios';

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            QuotasRelationManager::class,
            RegistrationsRelationManager::class,
        ];
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'view-non-member' => ViewNonMember::route('/{record}/non-member'),
            'edit' => EditMember::route('/{record}/edit'),
            'edit-non-member' => EditNonMember::route('/{record}/edit-non-member'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::current()?->hasPermission('members.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return User::current()?->hasPermission('members.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return User::current()?->hasPermission('members.edit') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return User::current()?->is_admin ?? false;
    }
}
