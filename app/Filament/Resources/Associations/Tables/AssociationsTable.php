<?php

namespace App\Filament\Resources\Associations\Tables;

use App\Enums\PaymentPeriod;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssociationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderBy('id')->limit(1))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Asociacion')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->toggleable(),
                TextColumn::make('payment_period')
                    ->label('Periodicidad')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        PaymentPeriod::Anual->value => PaymentPeriod::Anual->label(),
                        PaymentPeriod::Semestral->value => PaymentPeriod::Semestral->label(),
                        PaymentPeriod::Trimestral->value => PaymentPeriod::Trimestral->label(),
                        PaymentPeriod::Mensual->value => PaymentPeriod::Mensual->label(),
                        default => '-',
                    }),
                TextColumn::make('last_member_number')
                    ->label('Ultimo socio')
                    ->alignEnd(),
                TextColumn::make('last_receipt_number')
                    ->label('Ultimo recibo')
                    ->alignEnd(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
