<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del usuario')
                ->schema([
                    TextEntry::make('name')
                        ->label('Nombre')
                        ->columnSpan(1),
                    TextEntry::make('email')
                        ->label('Correo electrónico')
                        ->columnSpan(1),
                    TextEntry::make('user_type')
                        ->label('Rol')
                        ->badge()
                        ->formatStateUsing(fn (?UserType $state): string => $state?->label() ?? '—')
                        ->columnSpan(1),
                    IconEntry::make('is_admin')
                        ->label('Administrador del sistema')
                        ->boolean()
                        ->columnSpan(1),
                    TextEntry::make('created_at')
                        ->label('Creado el')
                        ->date('d/m/Y')
                        ->columnSpan(1),
                ])->columns(2),
        ]);
    }
}
