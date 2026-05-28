<?php

namespace App\Filament\Resources\DocumentTemplates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class DocumentTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información general')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Medium)
                            ->color('primary')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->columnSpan(1),
                        TextEntry::make('created_at')
                            ->label('Creada')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Variables detectadas')
                    ->schema([
                        TextEntry::make('detected_variables')
                            ->label('Variables reconocidas')
                            ->state(fn ($record): string => is_array($record->detected_variables) && count($record->detected_variables) > 0
                                ? implode('   ', array_map(fn (string $k) => '${'.$k.'}', $record->detected_variables))
                                : '—')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
