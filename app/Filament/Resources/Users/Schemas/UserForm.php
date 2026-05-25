<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del usuario')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn ($record) => $record === null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->minLength(8),
                    Select::make('user_type')
                        ->label('Rol')
                        ->options(UserType::class)
                        ->native(false)
                        ->required()
                        ->helperText('El Administrador tiene acceso total. El resto según los permisos configurados en "Permisos por rol".'),
                ])
                ->columns(2),
        ]);
    }
}
