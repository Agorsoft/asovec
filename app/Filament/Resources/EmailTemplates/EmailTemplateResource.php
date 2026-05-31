<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Clusters\Plantillas\PlantillasCluster;
use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Resources\EmailTemplates\Pages\ViewEmailTemplate;
use App\Filament\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use App\Filament\Resources\EmailTemplates\Tables\EmailTemplatesTable;
use App\Models\EmailTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $cluster = PlantillasCluster::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $modelLabel = 'Plantilla de email';

    protected static ?string $pluralModelLabel = 'Plantillas de email';

    protected static ?string $navigationLabel = 'Plantillas de email';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'view' => ViewEmailTemplate::route('/{record}'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return User::current()?->hasPermission('email_templates.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return User::current()?->hasPermission('email_templates.create') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return User::current()?->hasPermission('email_templates.edit') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return User::current()?->hasPermission('email_templates.edit') ?? false;
    }
}
