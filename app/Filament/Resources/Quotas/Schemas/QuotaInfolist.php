<?php

namespace App\Filament\Resources\Quotas\Schemas;

use App\Enums\QuotaStatus;
use App\Models\Quota;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class QuotaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle de cuota')
                    ->schema([
                        TextEntry::make('member.id')
                            ->label('Socio')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->state(function (Quota $record): string {
                                if (! $record->member) {
                                    return '-';
                                }

                                return $record->member->member_number_formatted.' - '.$record->member->full_name;
                            })
                            ->columnSpanFull(),
                        TextEntry::make('payment_method')
                            ->label('Método de Pago')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('quotaType.name')
                            ->label('Tipo de cuota')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('due_date')
                            ->label('Fecha de vencimiento')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('receipt_number')
                            ->label('Número de Recibo')
                            ->placeholder('-')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(1)
                    ->columns(2),
                Section::make('Información de la Cuota')
                    ->schema([
                        TextEntry::make('period_start')
                            ->label('Inicio del Período')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('period_end')
                            ->label('Fin del Período')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->numeric()
                            ->suffix(' EUR')
                            ->columnSpan(1),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (QuotaStatus $state): string => $state->color())
                            ->columnSpan(1),
                        TextEntry::make('paid_at')
                            ->label('Fecha de Pago')
                            ->dateTime('d/m/Y')
                            ->placeholder('-')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(1)
                    ->columns(2),
                Section::make('Notas')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);

    }
}
