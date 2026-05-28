<?php

namespace App\Filament\Resources\QuotaTypes\Schemas;

use App\Enums\PaymentPeriod;
use App\Services\QuotaTypeResolver;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuotaTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del tipo de cuota')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),
                        Select::make('period_type')
                            ->label('Periodo')
                            ->options(PaymentPeriod::class)
                            ->native(false)
                            ->required()
                            ->columnSpan(2),
                        DatePicker::make('period_reference_date')
                            ->label('Referencia del periodo')
                            ->default(fn (): string => QuotaTypeResolver::thresholdDate())
                            ->required()
                            ->columnSpan(1),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('default_amount')
                            ->label('Importe por defecto')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('EUR')
                            ->columnSpan(1),
                        TextInput::make('grace_days')
                            ->label('Días de gracia')
                            ->numeric()
                            ->default(15)
                            ->minValue(0)
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('refund_days')
                            ->label('Días de devolución')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required()
                            ->columnSpan(1),
                        Toggle::make('align_period_start')
                            ->label('Alinear inicio del periodo')
                            ->default(true)
                            ->required()
                            ->inline(false)
                            ->columnSpan(1),
                    ])->columns(5),
            ])->columns(1);
    }
}
