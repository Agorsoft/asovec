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
        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn ($record) => $record === null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->confirmed()
                            ->minLength(8)
                            ->columnSpan(1),
                        TextInput::make('password_confirmation')
                            ->label('Repetir contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn ($record) => $record === null)
                            ->dehydrated(false)
                            ->columnSpan(1),
                    ])->columns(2)->columnSpan(2),
                Section::make('Permisos avanzados')
                    ->schema([
                        Select::make('user_type')
                            ->label('Rol')
                            ->options(UserType::class)
                            ->native(false)
                            ->required()
                            ->helperText('El Administrador tiene acceso total. El resto según los permisos configurados en "Permisos por rol".'),
                    ])->columns(1)->columnSpan(1),
            ])->columns(3);
    }
}
