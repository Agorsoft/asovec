<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Models\Association;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('source_type')
                    ->default(TransactionSourceType::Manual->value),
                Section::make('Detalles de Transacción')
                    ->schema([
                        TextInput::make('description')
                            ->label('Descripción')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('type')
                            ->options(TransactionType::class)
                            ->label('Tipo')
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('date')
                            ->label('Fecha')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),
                        TextInput::make('amount')
                            ->label('Importe')
                            ->required()
                            ->numeric()
                            ->prefix('EUR')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(2)
                    ->columns(3),
                Section::make('Información de Pago')
                    ->description('Las transacciones creadas desde este formulario se registran como movimientos manuales. Las cuotas e inscripciones generan sus propios asientos automáticamente.')
                    ->schema([
                        Select::make('payment_method')
                            ->label('Método de pago')
                            ->options(fn (): array => Association::paymentMethodOptions())
                            ->default(fn (): ?string => Association::defaultPaymentMethod())
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        TextInput::make('receipt_number')
                            ->label('Nº recibo')
                            ->columnSpan(1),
                    ])
                    ->columns(2),
                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->hiddenLabel()
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->columns(1),

            ])
            ->columns(3);

        // return $schema
        //     ->components([
        //         Section::make('Datos de transacción')
        //             ->schema([
        //                 Select::make('type')
        //                     ->label('Tipo')
        //                     ->options(TransactionType::class)
        //                     ->native(false)
        //                     ->required()
        //                     ->validationMessages([
        //                         'required' => 'El tipo es obligatorio.',
        //                     ])
        //                     ->columnSpan(1),
        //                 TextInput::make('amount')
        //                     ->label('Importe')
        //                     ->numeric()
        //                     ->step('0.01')
        //                     ->minValue(0)
        //                     ->prefix('€')
        //                     ->required()
        //                     ->validationMessages([
        //                         'required' => 'El importe es obligatorio.',
        //                     ])
        //                     ->columnSpan(1),
        //                 DatePicker::make('date')
        //                     ->label('Fecha')
        //                     ->displayFormat('d/m/Y')
        //                     ->required()
        //                     ->validationMessages([
        //                         'required' => 'La fecha es obligatoria.',
        //                     ])
        //                     ->columnSpan(1),
        //                 Select::make('category_id')
        //                     ->label('Categoría')
        //                     ->relationship('category', 'name')
        //                     ->searchable()
        //                     ->preload()
        //                     ->columnSpan(2),
        //                 TextInput::make('payment_method')
        //                     ->label('Método de pago')
        //                     ->maxLength(50)
        //                     ->columnSpan(1),
        //                 TextInput::make('receipt_number')
        //                     ->label('Nº de recibo')
        //                     ->maxLength(255)
        //                     ->columnSpan(1),
        //                 TextInput::make('description')
        //                     ->label('Descripción')
        //                     ->required()
        //                     ->maxLength(255)
        //                     ->validationMessages([
        //                         'required' => 'La descripción es obligatoria.',
        //                     ])
        //                     ->columnSpanFull(),
        //                 TextInput::make('reference_type')
        //                     ->label('Tipo referencia')
        //                     ->maxLength(255)
        //                     ->placeholder('App\\Models\\Quota')
        //                     ->columnSpan(1),
        //                 TextInput::make('reference_id')
        //                     ->label('ID referencia')
        //                     ->numeric()
        //                     ->columnSpan(1),
        //                 TextInput::make('parent_transaction_id')
        //                     ->label('Transacción padre')
        //                     ->numeric()
        //                     ->columnSpan(1),
        //                 Textarea::make('notes')
        //                     ->label('Notas')
        //                     ->rows(3)
        //                     ->columnSpanFull(),
        //             ])
        //             ->columns(3),
        //     ]);
    }
}
