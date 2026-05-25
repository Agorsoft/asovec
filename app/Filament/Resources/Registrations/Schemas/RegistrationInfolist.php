<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Registration;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Evento')
                    ->schema([
                        TextEntry::make('event.name')
                            ->label('Evento')
                            ->columnSpan(4),
                        TextEntry::make('registration_number')
                            ->label('Nº inscripción')
                            ->placeholder('—')
                            ->columnSpan(3),
                        TextEntry::make('registration_sequence')
                            ->label('Secuencia')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('amount')
                            ->label('Importe base')
                            ->money('EUR')
                            ->columnSpan(2),
                        TextEntry::make('surcharge_amount')
                            ->label('Recargo')
                            ->money('EUR')
                            ->columnSpan(2),
                        TextEntry::make('total_amount')
                            ->label('Importe total')
                            ->state(fn (Registration $record): string => $record->total_amount)
                            ->money('EUR')
                            ->columnSpan(2),
                    ])
                    ->columnSpan(1)
                    ->columns(7),
                Section::make('Datos de Inscripción')
                    ->schema([
                        TextEntry::make('registration_type')
                            ->label('Tipo de inscripción')
                            ->badge()
                            ->columnSpan(1),
                        TextEntry::make('member.full_name')
                            ->label('Participante')
                            ->placeholder('—')
                            ->columnSpan(2),
                        TextEntry::make('sponsorMember.full_name')
                            ->label('Acompañante')
                            ->placeholder('—')
                            ->visible(fn (Registration $record): bool => $record->registration_type === RegistrationType::Acompanante)
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->placeholder('—')
                            ->badge()
                            ->color(fn (?RegistrationStatus $state): string => $state?->color() ?? 'gray')
                            ->columnSpan(1),
                        TextEntry::make('payment_method')
                            ->label('Método de pago')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('paid_at')
                            ->label('Fecha de pago')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(1)
                    ->columns(3),
                TextEntry::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
