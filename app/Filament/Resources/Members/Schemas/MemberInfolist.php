<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->schema([
                        TextEntry::make('first_name')->label('Nombre')->default('Sin datos')->weight(FontWeight::Bold)->size(TextSize::Medium)->color('primary')->columnSpan(2),
                        TextEntry::make('last_name')->label('Apellidos')->default('Sin datos')->weight(FontWeight::Bold)->size(TextSize::Medium)->color('primary')->columnSpan(2),
                        TextEntry::make('member_number')->label('Numero de socio')->default('Sin datos')->weight(FontWeight::Bold)->size(TextSize::Medium)->color('primary')->columnSpan(2),
                        TextEntry::make('dni')->label('DNI')->default('Sin datos')->columnSpan(2),
                        TextEntry::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->date('d/m/Y')
                            ->formatStateUsing(fn ($state): string => ($state instanceof \DateTimeInterface) ? $state->format('d/m/Y') : (filled($state) ? (string) $state : 'Sin datos'))
                            ->default('Sin datos')
                            ->columnSpan(2),
                        TextEntry::make('gender')->label('Genero')->default('Sin datos')->columnSpan(2),
                        Section::make('')
                            ->schema([
                                TextEntry::make('address')->label('Direccion')->default('Sin datos')->columnSpan(6),
                                TextEntry::make('postal_code')->label('Codigo postal')->default('Sin datos')->columnSpan(2),
                                TextEntry::make('city')->label('Localidad')->default('Sin datos')->columnSpan(4),
                                TextEntry::make('province')->label('Provincia')->default('Sin datos')->columnSpan(3),
                                TextEntry::make('phone')->label('Telefono')->default('Sin datos')->columnSpan(3),
                                TextEntry::make('email')->label('Correo electronico')->default('Sin datos')->columnSpan(6),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'my-4']),
                        Section::make('Comunicaciones')
                            ->schema([
                                IconEntry::make('whatsapp_opt_in')->label('WhatsApp')->boolean()->columnSpan(4),
                                IconEntry::make('sms_opt_in')->label('Mensaje SMS')->boolean()->columnSpan(4),
                                IconEntry::make('email_opt_in')->label('Correo electrónico')->boolean()->columnSpan(4),
                            ])->columns(12)->columnSpanFull(),
                        Section::make('Datos Bancarios')
                            ->visible(fn ($record): bool => (($record?->payment_method_id instanceof \BackedEnum) ? $record->payment_method_id->value : (string) $record?->payment_method_id) === PaymentMethod::Domiciliacion->value)
                            ->schema([
                                TextEntry::make('bank_account_holder')->label('Titular de la cuenta')->default('Sin datos')->columnSpan(3),
                                TextEntry::make('bank_iban')->label('IBAN')->default('Sin datos')->columnSpan(3),
                                TextEntry::make('bank_bic')->label('BIC')->default('Sin datos')->columnSpan(2),
                                TextEntry::make('sepa_mandate_reference')->label('Referencia mandato')->default('Sin datos')->columnSpan(2),
                                TextEntry::make('sepa_mandate_status')->label('Estado mandato')->default('Sin datos')->columnSpan(2),
                                TextEntry::make('sepa_sequence_type')->label('Secuencia SEPA')->default('Sin datos')->columnSpan(2),
                                TextEntry::make('sepa_mandate_signed_at')
                                    ->label('Firma mandato')
                                    ->date('d/m/Y')
                                    ->formatStateUsing(fn ($state): string => ($state instanceof \DateTimeInterface) ? $state->format('d/m/Y') : (filled($state) ? (string) $state : 'Sin datos'))
                                    ->default('Sin datos')
                                    ->columnSpan(2),
                            ])->columnSpanFull()->columns(8)->extraAttributes(['class' => 'mb-4']),
                        TextEntry::make('notes')->label('Notas')->default('Sin datos')->columnSpanFull(),
                    ])->columns(6)->columnSpan(2),
                Section::make('Asociado')
                    ->schema([
                        ImageEntry::make('photo_path')
                            ->label('Foto')
                            ->disk('public')
                            ->extraImgAttributes(['style' => 'height: auto !important; max-width: 160px;'])
                            ->defaultImageUrl(asset('images/member-placeholder.svg'))
                            ->extraAttributes(['class' => 'readonly-entry mb-4 justify-center'])
                            ->columnSpanFull(),
                        TextEntry::make('joined_at')
                            ->label('Fecha de alta')
                            ->date('d/m/Y')

                            ->formatStateUsing(fn ($state): string => ($state instanceof \DateTimeInterface) ? $state->format('d/m/Y') : (filled($state) ? (string) $state : 'Sin datos'))
                            ->default('Sin datos')
                            ->columnSpan(1),
                        TextEntry::make('left_at')
                            ->label('Fecha de baja')
                            ->date('d/m/Y')

                            ->formatStateUsing(fn ($state): string => ($state instanceof \DateTimeInterface) ? $state->format('d/m/Y') : (filled($state) ? (string) $state : 'Sin datos'))
                            ->default('Sin datos')
                            ->columnSpan(1),
                        TextEntry::make('payment_method_id')->label('Metodo de pago')->default('Sin datos')->columnSpan(1),
                        TextEntry::make('status')->label('Estado')->badge()->color(fn (MemberStatus $state): string => $state->color())->default('Sin datos')->columnSpan(1),
                    ])->columns(2)->columnSpan(1),
            ])->columns(3);
    }
}
