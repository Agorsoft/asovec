<?php

namespace App\Filament\Resources\LabelTemplates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LabelTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(3),
                        TextInput::make('description')
                            ->label('Descripción')
                            ->maxLength(255)
                            ->columnSpan(5),
                        TextInput::make('brand')
                            ->label('Marca')
                            ->placeholder('Avery, APLI, Herma…')
                            ->maxLength(100)
                            ->columnSpan(2),
                        TextInput::make('brand_code')
                            ->label('Código de referencia')
                            ->placeholder('L7160, 4731…')
                            ->maxLength(50)
                            ->columnSpan(2),
                    ])->columns(12)->columnSpanFull(),

                Section::make('Página')
                    ->description('Dimensiones de la hoja de papel (normalmente A4: 210 × 297 mm)')
                    ->schema([
                        TextInput::make('page_width_mm')
                            ->label('Ancho de página (mm)')
                            ->numeric()
                            ->required()
                            ->default(210)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(2),
                        TextInput::make('page_height_mm')
                            ->label('Alto de página (mm)')
                            ->numeric()
                            ->required()
                            ->default(297)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(2),
                        TextInput::make('columns')
                            ->label('Columnas')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(20)
                            ->columnSpan(2),
                        TextInput::make('rows')
                            ->label('Filas')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(8)
                            ->minValue(1)
                            ->maxValue(30)
                            ->columnSpan(2),
                        TextInput::make('margin_left_mm')
                            ->label('Margen izquierdo (mm)')
                            ->numeric()
                            ->required()
                            ->default(7.20)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(2),
                        TextInput::make('margin_top_mm')
                            ->label('Margen superior (mm)')
                            ->numeric()
                            ->required()
                            ->default(13.50)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(2),
                    ])->columns(12)->columnSpanFull(),

                Section::make('Dimensiones de la etiqueta')
                    ->schema([
                        TextInput::make('label_width_mm')
                            ->label('Ancho (mm)')
                            ->numeric()
                            ->required()
                            ->default(64.00)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(1),
                        TextInput::make('label_height_mm')
                            ->label('Alto (mm)')
                            ->numeric()
                            ->required()
                            ->default(33.90)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(1),
                        TextInput::make('horizontal_gap_mm')
                            ->label('Separación horizontal (mm)')
                            ->numeric()
                            ->required()
                            ->default(2.50)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(1),
                        TextInput::make('vertical_gap_mm')
                            ->label('Separación vertical (mm)')
                            ->numeric()
                            ->required()
                            ->default(0.00)
                            ->step(0.01)
                            ->suffix('mm')
                            ->columnSpan(1),
                    ])->columns(4)->columnSpan(1),

                Section::make('Tipografía')
                    ->schema([
                        TextInput::make('font_size')
                            ->label('Tamaño de fuente (pt)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->default(10)
                            ->minValue(6)
                            ->maxValue(18)
                            ->suffix('pt')
                            ->columnSpan(1),
                        TextInput::make('line_height')
                            ->label('Interlineado')
                            ->numeric()
                            ->required()
                            ->default(1.30)
                            ->step(0.05)
                            ->helperText('Multiplicador sobre el tamaño de fuente')
                            ->columnSpan(1),
                        TextInput::make('inner_padding_mm')
                            ->label('Relleno interior (mm)')
                            ->numeric()
                            ->required()
                            ->default(2.00)
                            ->step(0.5)
                            ->suffix('mm')
                            ->helperText('Margen interno de cada etiqueta')
                            ->columnSpan(1),
                    ])->columns(3)->columnSpan(1),
            ])->columns(2);
    }
}
