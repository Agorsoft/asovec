<?php

namespace App\Filament\Resources\LabelTemplates\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LabelTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('brand')
                    ->label('Marca')
                    ->formatStateUsing(fn ($state, $record) => implode(' ', array_filter([$state, $record->brand_code])))
                    ->placeholder('—'),

                TextColumn::make('columns')
                    ->label('Colúmnas × Filas')
                    ->formatStateUsing(fn ($state, $record) => "{$record->columns}×{$record->rows}")
                    ->alignCenter(),

                TextColumn::make('label_width_mm')
                    ->label('Etiqueta (mm)')
                    ->formatStateUsing(fn ($state, $record) => "{$record->label_width_mm}×{$record->label_height_mm}")
                    ->alignCenter(),

                TextColumn::make('labels_per_page')
                    ->label('Etiquetas/hoja')
                    ->state(fn ($record) => $record->labels_per_page)
                    ->alignCenter(),

                TextColumn::make('font_size')
                    ->label('Fuente')
                    ->formatStateUsing(fn ($state) => "{$state} pt")
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver'),
                    EditAction::make()
                        ->label('Editar'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
