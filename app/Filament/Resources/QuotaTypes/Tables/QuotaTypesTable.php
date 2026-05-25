<?php

namespace App\Filament\Resources\QuotaTypes\Tables;

use App\Enums\PaymentPeriod;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotaTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period_type')
                    ->label('Periodicidad')
                    ->formatStateUsing(function ($state): string {
                        if (is_object($state)) {
                            if (method_exists($state, 'label')) {
                                return (string) $state->label();
                            }

                            if ($state instanceof \BackedEnum) {
                                return (string) ($state->name ?? $state->value ?? $state);
                            }

                            if (method_exists($state, '__toString')) {
                                return (string) $state;
                            }
                        }

                        if (is_string($state) || is_int($state)) {
                            $enum = PaymentPeriod::tryFrom((string) $state);
                            if (is_object($enum)) {
                                if (method_exists($enum, 'label')) {
                                    return (string) $enum->label();
                                }

                                return (string) ($enum->name ?? $enum->value ?? $enum);
                            }

                            return (string) $state;
                        }

                        return '—';
                    })
                    ->sortable(),
                TextColumn::make('default_amount')
                    ->label('Importe por defecto')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('grace_days')
                    ->label('Días gracia')
                    ->sortable(),
                   TextColumn::make('refund_days')
                       ->label('Días devolución')
                       ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
                IconColumn::make('align_period_start')
                    ->label('Alinear inicio')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('period_type')
                    ->label('Periodicidad')
                    ->options(PaymentPeriod::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver'),
                    EditAction::make()
                        ->label('Editar'),
                ]),
            ])
            ->toolbarActions([]);
    }
}
