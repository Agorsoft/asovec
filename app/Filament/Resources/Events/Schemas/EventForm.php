<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventStatus;
use App\Enums\EventType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Evento')
                    ->schema([
                        TextInput::make('event_code')
                            ->label('Código')
                            ->maxLength(30)
                            ->unique(ignoreRecord: true)
                            ->readOnly()
                            ->dehydrated(false)
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->placeholder('Se genera automáticamente')
                            ->columnSpan(1),
                        Select::make('event_type')
                            ->label('Tipo')
                            ->options(EventType::class)
                            ->default(EventType::Evento)
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1)
                    ->columns(2),
                Section::make('Datos principales')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(EventStatus::class)
                            ->default(EventStatus::Abierto)
                            ->live()
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('start_date')
                            ->label('Inicio')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('end_date')
                            ->label('Fin')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('registration_deadline')
                            ->label('Fecha límite')
                            ->displayFormat('d/m/Y')
                            ->columnSpan(1),
                        TextInput::make('max_companions_per_member')
                            ->label('Nº acompañantes')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('base_price')
                            ->label('Precio base')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('EUR')
                            ->columnSpan(1),
                        TextInput::make('companion_surcharge')
                            ->label('Recargo acompañante')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->readOnly(fn (Get $get): bool => (int) ($get('max_companions_per_member') ?? 0) === 0)
                            ->prefix('EUR')
                            ->columnSpan(1),
                        TextInput::make('non_member_surcharge')
                            ->label('Recargo No Socio')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('EUR')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(4),
                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas internas')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('cancellation_reason')
                            ->label('Motivo de cancelación')
                            ->rows(2)
                            ->visible(fn (Get $get): bool => ($get('status') instanceof EventStatus
                            ? $get('status')
                            : EventStatus::tryFrom((string) $get('status'))) === EventStatus::Cancelado)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(1),
            ])->columns(3);
    }
}
