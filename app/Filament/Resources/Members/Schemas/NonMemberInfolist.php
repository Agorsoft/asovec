<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NonMemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->schema([
                        TextEntry::make('first_name')->label('Nombre')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(2),
                        TextEntry::make('last_name')->label('Apellidos')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(4),
                        TextEntry::make('dni')->label('DNI')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(2),
                        TextEntry::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->date('d/m/Y')
                            ->extraAttributes(['class' => 'readonly-entry'])
                            ->formatStateUsing(fn ($state): string => ($state instanceof \DateTimeInterface) ? $state->format('d/m/Y') : (filled($state) ? (string) $state : 'Sin datos'))
                            ->default('Sin datos')
                            ->columnSpan(2),
                        TextEntry::make('gender')->label('Género')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(2),
                        Section::make('')
                            ->schema([
                                TextEntry::make('address')->label('Dirección')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(6),
                                TextEntry::make('postal_code')->label('Código postal')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(2),
                                TextEntry::make('city')->label('Localidad')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(4),
                                TextEntry::make('province')->label('Provincia')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(3),
                                TextEntry::make('phone')->label('Teléfono')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(3),
                                TextEntry::make('email')->label('Correo electrónico')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpan(6),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'my-4']),
                        TextEntry::make('notes')->label('Notas')->extraAttributes(['class' => 'readonly-entry'])->default('Sin datos')->columnSpanFull(),
                    ])->columns(6)->columnSpanFull(),
            ]);
    }
}
