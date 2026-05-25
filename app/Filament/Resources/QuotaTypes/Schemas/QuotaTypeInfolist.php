<?php

namespace App\Filament\Resources\QuotaTypes\Schemas;

use App\Enums\PaymentPeriod;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class QuotaTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle del tipo de cuota')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->columnSpan(2),
                        TextEntry::make('period_type')
                            ->label('Periodicidad')
                            ->formatStateUsing(fn ($state): string => ($state instanceof PaymentPeriod)
                                ? $state->label()
                                : (PaymentPeriod::tryFrom((string) $state)?->label() ?? (string) $state))
                            ->columnSpan(2),
                        TextEntry::make('period_reference_date')
                            ->label('Fecha de referencia')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('default_amount')
                            ->label('Importe por defecto')
                            ->money('EUR')
                            ->columnSpan(1),
                        TextEntry::make('grace_days')
                            ->label('Días de gracia')
                            ->columnSpan(1),
                        TextEntry::make('refund_days')
                            ->label('Días de devolución')
                            ->columnSpan(1),
                        TextEntry::make('sort_order')
                            ->label('Orden')
                            ->columnSpan(1),
                        IconEntry::make('align_period_start')
                            ->label('Alinear inicio')
                            ->boolean()
                            ->columnSpan(1),
                    ])
                    ->columns(5),
            ])->columns(1);
    }
}
