<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Evento')
                    ->schema([
                        TextEntry::make('event_code')
                            ->label('Código')
                            ->columnSpan(1),
                        TextEntry::make('event_type')
                            ->label('Tipo')
                            ->columnSpan(1),
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1)
                    ->columns(2),
                Section::make('Datos principales')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (EventStatus $state): string => $state->color())
                            ->columnSpan(1),
                        TextEntry::make('start_date')
                            ->label('Inicio')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('end_date')
                            ->label('Fin')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('registration_deadline')
                            ->label('Límite inscripción')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('max_companions_per_member')
                            ->label('Máx. acompañantes')
                            ->columnSpan(1),
                        TextEntry::make('base_price')
                            ->label('Precio base')
                            ->money('EUR')
                            ->columnSpan(1),
                        TextEntry::make('companion_surcharge')
                            ->label('Recargo acompañante')
                            ->money('EUR')
                            ->columnSpan(1),
                        TextEntry::make('non_member_surcharge')
                            ->label('Recargo no socio')
                            ->money('EUR')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(4),
                Section::make('Notas')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('cancellation_reason')
                            ->label('Motivo de cancelación')
                            ->placeholder('—')
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
