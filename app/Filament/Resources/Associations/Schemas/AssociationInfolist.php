<?php

namespace App\Filament\Resources\Associations\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class AssociationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Datos de la asociacion')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Asociación')
                            ->schema([
                                Section::make('Datos de Asociación')
                                    ->schema([
                                        TextEntry::make('name')->label('Nombre')->columnSpan(4)->default('Sin datos')->weight(FontWeight::Bold)->size(TextSize::Medium)->color('primary'),
                                        TextEntry::make('tax_id')->label('NIF / CIF')->columnSpan(2)->default('Sin datos')->weight(FontWeight::Bold)->size(TextSize::Medium)->color('primary'),
                                        TextEntry::make('address')->label('Dirección')->columnSpan(4)->default('Sin datos'),
                                        TextEntry::make('postal_code')->label('Código postal')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('city')->label('Localidad')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('province')->label('Provincia')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('country')->label('País')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('email')->label('Correo electrónico')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('website')->label('Web')->url(fn (?string $state): ?string => $state)->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('phone')->label('Teléfono')->columnSpan(2)->default('Sin datos'),
                                        TextEntry::make('registration_entity')->label('Entidad de registro')->columnSpan(5)->default('Sin datos'),
                                        TextEntry::make('registration_number')->label('Nº registro')->columnSpan(1)->default('Sin datos'),
                                    ])
                                    ->columnSpan(3)
                                    ->columns(6),
                                Section::make('Logo y observaciones')
                                    ->schema([
                                        ImageEntry::make('logo_path')
                                            ->label('Logo')
                                            ->disk('public')
                                            ->extraImgAttributes(['style' => 'height: auto !important; max-width: 100%;'])
                                            ->defaultImageUrl(asset('images/logo.svg'))
                                            ->extraAttributes(['class' => 'readonly-entry mb-4 justify-center'])
                                            ->columnSpanFull(),
                                        TextEntry::make('observations')->label('Observaciones')->columnSpanFull()->default('Sin datos'),
                                    ])->columnSpan(2),
                            ])->columns(5),
                        Tab::make('Finanzas')
                            ->schema([
                                Section::make('Pagos')
                                    ->headerActions([
                                        Action::make('printSepa')
                                            ->label('Imprimir SEPA')
                                            ->icon('heroicon-o-printer')
                                            ->url(fn ($livewire): string => route('associations.sepa.print', $livewire->getRecord()))
                                            ->openUrlInNewTab()
                                            ->visible(fn ($record): bool => collect($record?->payment_methods ?? [])->map(fn ($v) => ($v instanceof \BackedEnum) ? $v->value : (string) $v)->contains(PaymentMethod::Domiciliacion->value) && filled($record?->iban)),
                                    ])
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('payment_methods')
                                                    ->label('Métodos de pago')
                                                    ->formatStateUsing(fn ($state): string => collect($state ?? [])->map(fn ($v): string => ($v instanceof \BackedEnum) ? $v->label() : (PaymentMethod::tryFrom($v)?->label() ?? (string) $v))->join(', '))
                                                    ->default('Sin datos')
                                                    ->columnSpan(2),
                                                TextEntry::make('payment_period')
                                                    ->label('Periodicidad')
                                                    ->formatStateUsing(fn ($state): string => ($state instanceof \BackedEnum) ? $state->label() : (PaymentPeriod::tryFrom($state)?->label() ?? (string) $state))
                                                    ->default('Sin datos')
                                                    ->columnSpan(1),
                                                TextEntry::make('bank_name')->label('Nombre del banco')->default('Sin datos')->columnSpan(1),
                                                TextEntry::make('iban')->label('IBAN')->default('Sin datos')->columnSpan(1),
                                                TextEntry::make('bic')->label('BIC')->default('Sin datos')->columnSpan(1),
                                            ])
                                            ->columnSpanFull()
                                            ->columns(3),

                                        Section::make('Datos bancarios')
                                            ->schema([
                                                Grid::make(5)
                                                    ->schema([
                                                        TextEntry::make('bank_branch')->label('Sucursal')->default('Sin datos')->columnSpan(2),
                                                        TextEntry::make('bank_address')->label('Dirección del banco')->default('Sin datos')->columnSpan(2),
                                                        TextEntry::make('bank_postal_code')->label('Código postal')->default('Sin datos')->columnSpan(1),
                                                        TextEntry::make('bank_city')->label('Localidad')->default('Sin datos')->columnSpan(1),
                                                        TextEntry::make('bank_province')->label('Provincia')->default('Sin datos')->columnSpan(1),
                                                        TextEntry::make('bank_country')->label('País')->columnSpan(2)->default('Sin datos')->columnSpan(1),
                                                        TextEntry::make('creditor_identifier')->label('Identificador acreedor SEPA')->default('Sin datos')->columnSpan(2),
                                                    ]),
                                            ])
                                            ->extraAttributes(['class' => 'mt-6'])
                                            ->visible(fn ($record): bool => collect($record?->payment_methods ?? [])->map(fn ($v) => ($v instanceof \BackedEnum) ? $v->value : (string) $v)->intersect([
                                                PaymentMethod::Domiciliacion->value,
                                            ])->isNotEmpty()),
                                    ]),
                            ]),
                        Tab::make('Numeración')
                            ->schema([
                                Section::make('Datos de Asociación')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextEntry::make('receipt_number_mask')
                                                    ->label('Máscara de recibo')
                                                    ->default('{Y:2}-{N:4}'),
                                                TextEntry::make('last_member_number')
                                                    ->label('Último número de socio')
                                                    ->numeric()
                                                    ->default('0'),
                                                TextEntry::make('last_receipt_number')
                                                    ->label('Último número de recibo')
                                                    ->numeric()
                                                    ->default('0'),
                                                Callout::make('Formato de máscara')
                                                    ->description('{YYYY}-{NNNN} o {Y:2}-{N:4}. {Y:4} = año (4 dígitos), {N:4} = número secuencial (rellenado con ceros). Ejemplo: 2026-0001')
                                                    ->info()
                                                    ->extraAttributes(['class' => 'mt-4'])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Correo')
                            ->schema([
                                Section::make('Configuración de correo')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('use_email')
                                                    ->label('Usar servicio de correo')
                                                    ->formatStateUsing(fn ($state): string => $state ? 'Sí' : 'No')
                                                    ->default('No')
                                                    ->columnSpan(2),
                                                TextEntry::make('mail_mailer')->label('Mailer')->default('Sin datos')->columnSpan(2),
                                                TextEntry::make('mail_host')->label('Host')->default('Sin datos')->columnSpan(2),
                                                TextEntry::make('mail_port')->label('Puerto')->default('Sin datos')->columnSpan(1),
                                                TextEntry::make('mail_encryption')->label('Cifrado')->default('Sin datos')->columnSpan(1),
                                                TextEntry::make('mail_username')->label('Usuario')->default('Sin datos')->columnSpan(2),
                                                TextEntry::make('mail_password')->label('Contraseña')->formatStateUsing(fn ($state): string => filled($state) ? '******' : 'Sin datos')->columnSpan(2),
                                                TextEntry::make('mail_from_address')->label('Remitente (dirección)')->default('Sin datos')->columnSpan(2),
                                                TextEntry::make('mail_from_name')->label('Remitente (nombre)')->default('Sin datos')->columnSpan(2),
                                            ])
                                            ->columnSpanFull()
                                            ->columns(3),
                                    ])
                                    ->visible(fn ($record): bool => (bool) ($record?->use_email ?? false)),
                            ]),
                    ]),
            ])->columns(1);

    }
}
