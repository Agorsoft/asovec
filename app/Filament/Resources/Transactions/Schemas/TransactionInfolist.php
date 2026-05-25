<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles de Transacción')
                    ->schema([
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->columnSpanFull(),
                        TextEntry::make('type')
                            ->label('Tipo Transacción')
                            ->badge()
                            ->color(fn (TransactionType $state): string => $state->color())
                            ->columnSpan(1),
                        TextEntry::make('source_label')
                            ->label('Origen')
                            ->state(fn (Transaction $record): string => $record->sourceLabel())
                            ->badge()
                            ->columnSpan(1),
                        TextEntry::make('date')
                            ->label('Fecha')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->money('EUR')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(4),
                Section::make('Información de Pago')
                    ->schema([
                        TextEntry::make('payment_method')
                            ->label('Método de pago')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('receipt_number')
                            ->label('Nº recibo')
                            ->placeholder('—')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                Section::make('Desglose asociado')
                    ->schema([
                        TextEntry::make('child_breakdown')
                            ->label('Partidas')
                            ->state(fn (Transaction $record): array => $record->childTransactions
                                ->map(fn (Transaction $child): string => $child->description.' · '.number_format((float) $child->amount, 2, ',', '.').' EUR')
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('Sin desglose')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Notas')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(1),
            ])
            ->columns(3);

    }
}
