<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            ->columnSpan(1),
                    ])->columns(3),
            ])->columns(1);
    }
}
