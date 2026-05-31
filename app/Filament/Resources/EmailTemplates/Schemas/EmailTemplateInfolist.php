<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class EmailTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
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
                        ->label('Concepto')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    IconEntry::make('is_active')
                        ->label('Plantilla activa para este concepto')
                        ->helperText('Solo puede haber una plantilla activa por concepto. Al activar ésta se desactivarán las demás del mismo tipo.')
                        ->boolean()
                        ->columnSpanFull(),
                ])
                ->columnSpan(1),

            Section::make('Contenido del email')
                ->schema([
                    TextEntry::make('subject')
                        ->label('Asunto')
                        ->columnSpanFull(),
                    TextEntry::make('body_html')
                        ->label('Cuerpo del mensaje')
                        ->columnSpanFull()
                        ->extraInputAttributes(['class' => 'min-h-[200px]'])
                        ->columnSpanFull(),
                ])
                ->columnSpan(1),
        ])->columns(2);
    }
}
