<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\Gender;
use App\Enums\MemberType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class NonMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->schema([
                        Hidden::make('member_type')->default(MemberType::NoSocio->value),
                        TextInput::make('first_name')->label('Nombre')->required()->validationMessages(['required' => 'El nombre es obligatorio.'])->columnSpan(2),
                        TextInput::make('last_name')->label('Apellidos')->required()->validationMessages(['required' => 'Los apellidos son obligatorios.'])->columnSpan(4),
                        TextInput::make('dni')->label('DNI/NIF')->unique(ignoreRecord: true)->validationMessages(['unique' => 'El DNI/NIF ya está en uso.'])->columnSpan(2),
                        DatePicker::make('birth_date')->label('Fecha de nacimiento')->displayFormat('d/m/Y')->columnSpan(2),
                        Select::make('gender')
                            ->label('Género')
                            ->options(Gender::class)
                            ->default(Gender::NoDefinido->value)
                            ->native(false)
                            ->required()
                            ->columnSpan(2)
                            ->placeholder('Seleccione'),
                        Section::make('')
                            ->schema([
                                TextInput::make('address')->label('Dirección')->columnSpan(6),
                                TextInput::make('postal_code')->label('C.P.')->columnSpan(2),
                                TextInput::make('city')->label('Localidad')->columnSpan(4),
                                TextInput::make('province')->label('Provincia')->columnSpan(3),
                                TextInput::make('phone')->label('Teléfono')->tel()->live(onBlur: true)->columnSpan(3),
                                TextInput::make('email')->label('Correo electrónico')->email()->live(onBlur: true)->columnSpan(6),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                        Section::make('Comunicaciones')
                            ->schema([
                                Checkbox::make('whatsapp_opt_in')
                                    ->label('WhatsApp')
                                    ->disabled(fn (Get $get): bool => ! filled($get('phone')))
                                    ->helperText(fn (Get $get): ?string => filled($get('phone')) ? null : 'Requiere teléfono')
                                    ->columnSpan(4),
                                Checkbox::make('sms_opt_in')
                                    ->label('Mensaje SMS')
                                    ->disabled(fn (Get $get): bool => ! filled($get('phone')))
                                    ->helperText(fn (Get $get): ?string => filled($get('phone')) ? null : 'Requiere teléfono')
                                    ->columnSpan(4),
                                Checkbox::make('email_opt_in')
                                    ->label('Correo electrónico')
                                    ->disabled(fn (Get $get): bool => ! filled($get('email')))
                                    ->helperText(fn (Get $get): ?string => filled($get('email')) ? null : 'Requiere correo electrónico')
                                    ->columnSpan(4),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                        Textarea::make('notes')->label('Notas')->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                    ])->columns(6)->columnSpanFull(),
            ]);
    }
}
