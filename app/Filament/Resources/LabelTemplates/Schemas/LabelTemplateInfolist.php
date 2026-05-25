<?php

namespace App\Filament\Resources\LabelTemplates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class LabelTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->columnSpan(3),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpan(5),
                        TextEntry::make('brand')
                            ->label('Marca')
                            ->placeholder('—')
                            ->columnSpan(2),
                        TextEntry::make('brand_code')
                            ->label('Referencia')
                            ->placeholder('—')
                            ->columnSpan(2),
                    ])
                    ->columns(12)
                    ->columnSpanFull(),

                Section::make('Página')
                    ->description('Dimensiones de la hoja de papel')
                    ->schema([
                        TextEntry::make('page_width_mm')
                            ->label('Ancho de página')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('page_height_mm')
                            ->label('Alto de página')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('columns')
                            ->label('Columnas')
                            ->columnSpan(1),
                        TextEntry::make('rows')
                            ->label('Filas')
                            ->columnSpan(1),
                        TextEntry::make('margin_left_mm')
                            ->label('Margen izquierdo')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('margin_top_mm')
                            ->label('Margen superior')
                            ->suffix(' mm')
                            ->columnSpan(1),
                    ])
                    ->columns(6)
                    ->columnSpanFull(),

                Section::make('Dimensiones de la etiqueta')
                    ->schema([
                        TextEntry::make('label_width_mm')
                            ->label('Ancho')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('label_height_mm')
                            ->label('Alto')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('horizontal_gap_mm')
                            ->label('Separación horizontal')
                            ->suffix(' mm')
                            ->columnSpan(1),
                        TextEntry::make('vertical_gap_mm')
                            ->label('Separación vertical')
                            ->suffix(' mm')
                            ->columnSpan(1),
                    ])
                    ->columns(4),

                Section::make('Tipografía')
                    ->schema([
                        TextEntry::make('font_size')
                            ->label('Tamaño de fuente')
                            ->suffix(' pt')
                            ->columnSpan(1),
                        TextEntry::make('line_height')
                            ->label('Interlineado')
                            ->columnSpan(1),
                        TextEntry::make('inner_padding_mm')
                            ->label('Relleno interior')
                            ->suffix(' mm')
                            ->columnSpan(1),
                    ])
                    ->columns(3),
            ]);
    }
}
