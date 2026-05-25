<?php

namespace App\Filament\Resources\Quotas\Schemas;

use App\Enums\QuotaStatus;
use App\Models\Member;
use App\Models\QuotaType;
use App\Services\QuotaPeriodService;
use App\Services\QuotaTypeResolver;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class QuotaForm
{
    public static function statusOptions(?string $operation = null): array
    {
        $statuses = $operation === 'edit'
            ? QuotaStatus::cases()
            : [QuotaStatus::Pendiente, QuotaStatus::Pagada];

        return collect($statuses)
            ->mapWithKeys(fn (QuotaStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Socio')
                    ->schema([
                        Select::make('member_id')
                            ->relationship('member', 'member_number')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => $record->member_number_formatted.' · '.$record->full_name)
                            ->label('Socio')
                            ->searchable(['member_number', 'first_name', 'last_name'])
                            ->preload()
                            ->live()
                            ->afterStateHydrated(function (mixed $state, Get $get, Set $set): void {
                                self::syncQuotaContext(
                                    filled($state) ? (int) $state : null,
                                    self::currentQuotaTypeId($get),
                                    $set,
                                    $get,
                                );
                            })
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                self::syncQuotaContext(
                                    filled($state) ? (int) $state : null,
                                    self::currentQuotaTypeId($get),
                                    $set,
                                    $get,
                                    true,
                                );
                            })
                            ->required()
                            ->columnSpanFull(),
                        Select::make('quota_type_id')
                            ->label('Tipo de cuota')
                            ->options(fn (Get $get): array => QuotaTypeResolver::options(self::currentQuotaTypeId($get)))
                            ->default(fn (): ?int => QuotaTypeResolver::defaultQuotaTypeId())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Get $get): bool => ! filled($get('member_id')))
                            ->visible(fn (Get $get): bool => filled($get('member_id')))
                            ->helperText(fn (Get $get): ?string => filled($get('member_id')) && ! QuotaTypeResolver::hasAvailableTypes(self::currentQuotaTypeId($get))
                                ? 'No hay tipos de cuota vigentes para el periodo por defecto.'
                                : null)
                            ->live()
                            ->afterStateHydrated(function (mixed $state, Get $get, Set $set): void {
                                if (! filled($get('member_id'))) {
                                    return;
                                }

                                self::syncQuotaContext(
                                    (int) $get('member_id'),
                                    filled($state) ? (int) $state : null,
                                    $set,
                                    $get,
                                );
                            })
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                if (! filled($get('member_id'))) {
                                    return;
                                }

                                self::syncQuotaContext(
                                    (int) $get('member_id'),
                                    filled($state) ? (int) $state : null,
                                    $set,
                                    $get,
                                    true,
                                );
                            })
                            ->columnSpanFull(),
                        TextInput::make('member_payment_method_badge')
                            ->label('Método de pago del socio')
                            ->default('Selecciona un socio')
                            ->readOnly()
                            ->dehydrated(false)
                            ->columnSpan(1),
                        Hidden::make('payment_method'),
                        DatePicker::make('due_date')
                            ->label('Fecha de Vencimiento')
                            ->displayFormat('d/m/Y')
                            ->required(fn (Get $get): bool => $get('status') !== QuotaStatus::Pagada->value)
                            ->visible(fn (Get $get): bool => $get('status') !== QuotaStatus::Pagada->value)
                            ->readOnly(),
                        TextInput::make('receipt_number')
                            ->label('Nº recibo')
                            ->readOnly()
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Section::make('Información de la Cuota')
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Periodo desde')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->readOnly(),
                        DatePicker::make('period_end')
                            ->label('Periodo hasta')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->readOnly(),
                        TextInput::make('amount')
                            ->label('Importe')
                            ->required()
                            ->numeric()
                            ->prefix('EUR'),
                        Select::make('status')
                            ->options(fn (string $operation): array => self::statusOptions($operation))
                            ->label('Estado')
                            ->default(QuotaStatus::Pendiente->value)
                            ->live()
                            ->required(),
                        DatePicker::make('paid_at')
                            ->label('Fecha de pago')
                            ->displayFormat('d/m/Y')
                            ->readOnly(),
                    ])
                    ->columns(2),
                Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    protected static function currentQuotaTypeId(Get $get): ?int
    {
        $quotaTypeId = $get('quota_type_id');

        return filled($quotaTypeId) ? (int) $quotaTypeId : null;
    }

    protected static function syncQuotaContext(
        ?int $memberId,
        ?int $quotaTypeId,
        Set $set,
        Get $get,
        bool $force = false,
    ): void {
        if (! $memberId) {
            $set('payment_method', null);
            $set('member_payment_method_badge', 'Selecciona un socio');
            $set('quota_type_id', null);

            if ($force) {
                self::resetComputedFields($set);
            }

            return;
        }

        $member = Member::query()->find($memberId);

        if (! $member) {
            $set('payment_method', null);
            $set('member_payment_method_badge', 'Selecciona un socio');

            if ($force) {
                self::resetComputedFields($set);
            }

            return;
        }

        $memberPaymentMethod = $member->payment_method_id ?? null;

        if ($memberPaymentMethod instanceof \BackedEnum) {
            $memberPaymentMethodValue = $memberPaymentMethod->value;
        } else {
            $memberPaymentMethodValue = filled($memberPaymentMethod) ? (string) $memberPaymentMethod : null;
        }

        $set('payment_method', $memberPaymentMethodValue);
        $set('member_payment_method_badge', filled($memberPaymentMethodValue) ? $memberPaymentMethodValue : 'Sin método configurado');

        $resolvedQuotaTypeId = QuotaTypeResolver::defaultQuotaTypeId($quotaTypeId);

        if ($resolvedQuotaTypeId !== $quotaTypeId) {
            $set('quota_type_id', $resolvedQuotaTypeId);
        }

        $quotaType = QuotaTypeResolver::resolve($resolvedQuotaTypeId);

        if (! $quotaType instanceof QuotaType) {
            if ($force) {
                self::resetComputedFields($set);
            }

            return;
        }

        if ($force || ! filled($get('amount'))) {
            $set('amount', QuotaPeriodService::defaultAmount($quotaType));
        }

        if ($force || ! filled($get('period_start')) || ! filled($get('period_end')) || ! filled($get('due_date'))) {
            $dates = QuotaPeriodService::forMember($member, $quotaType);

            $set('period_start', $dates['period_start']);
            $set('period_end', $dates['period_end']);
            $set('due_date', $dates['due_date']);
        }
    }

    protected static function resetComputedFields(Set $set): void
    {
        $set('amount', null);
        $set('period_start', null);
        $set('period_end', null);
        $set('due_date', null);
    }
}
