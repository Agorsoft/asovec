<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Association;
use App\Models\Event;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RegistrationForm
{
    /**
     * @return array<string, string>
     */
    public static function registrationTypeOptions(?Event $event): array
    {
        $options = [
            RegistrationType::Socio->value => RegistrationType::Socio->getLabel(),
            RegistrationType::NoSocio->value => RegistrationType::NoSocio->getLabel(),
        ];

        if ($event && (int) $event->max_companions_per_member > 0) {
            $options[RegistrationType::Acompanante->value] = RegistrationType::Acompanante->getLabel();
        }

        return $options;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Evento')
                    ->schema([
                        Select::make('event_id')
                            ->label('Evento')
                            ->relationship(
                                name: 'event',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::syncEventContext($get, $set);
                                self::syncPricing($get, $set);
                                self::syncParticipantState($get, $set);
                            })
                            ->columnSpan(4),
                        TextInput::make('registration_number')
                            ->label('Nº inscripción')
                            ->readOnly()
                            ->dehydrated(false)
                            ->placeholder('Se asigna al guardar')
                            ->columnSpan(3),
                        TextInput::make('registration_sequence')
                            ->label('Secuencia')
                            ->readOnly()
                            ->dehydrated(false)
                            ->placeholder('Se asigna al guardar')
                            ->numeric()
                            ->columnSpan(1),
                        TextInput::make('amount')
                            ->label('Importe base')
                            ->readOnly()
                            ->required()
                            ->numeric()
                            ->prefix('EUR')
                            ->columnSpan(2),
                        TextInput::make('surcharge_amount')
                            ->label('Recargo')
                            ->readOnly()
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('EUR')
                            ->columnSpan(2),
                        TextInput::make('total_amount')
                            ->label('Importe total')
                            ->afterStateHydrated(function (mixed $state, Get $get, Set $set): void {
                                self::syncTotalAmount($get, $set);
                            })
                            ->readOnly()
                            ->dehydrated(false)
                            ->prefix('EUR')
                            ->columnSpan(2),
                    ])
                    ->columnSpan(1)
                    ->columns(7),
                Section::make('Datos de Inscripción')
                    ->schema([
                        Select::make('registration_type')
                            ->label('Tipo de inscripción')
                            ->options(fn (Get $get): array => self::registrationTypeOptions(self::currentEvent($get)))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::syncPricing($get, $set);
                                self::syncParticipantState($get, $set);
                            })
                            ->columnSpan(1),
                        Select::make('member_id')
                            ->label(fn (Get $get): string => self::participantLabel($get))
                            ->options(fn (Get $get): array => self::memberOptions($get))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get): void {
                                self::notifyPendingMemberForNoSocio($get);
                            })
                            ->required()
                            ->createOptionAction(fn (Action $action, Get $get): Action => $action->hidden(
                                fn (): bool => ! self::canCreateMemberOption(self::currentType($get)),
                            ))
                            ->createOptionForm(fn (Get $get): ?array => self::currentType($get) === RegistrationType::NoSocio
                                ? self::nonMemberCreateForm()
                                : null)
                            ->createOptionUsing(fn (array $data): int => self::createNonMember($data))
                            ->columnSpan(2),
                        Select::make('sponsor_member_id')
                            ->label('Acompañante')
                            ->options(fn (Get $get): array => self::sponsorOptions($get))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Get $get): void {
                                self::notifyPendingCompanionForAcompanante($get);
                            })
                            ->createOptionAction(fn (Action $action, Get $get): Action => $action->hidden(
                                fn (): bool => ! self::canCreateSponsorOption(self::currentType($get)),
                            ))
                            ->createOptionForm(fn (Get $get): ?array => self::currentType($get) === RegistrationType::Acompanante
                                ? self::nonMemberCreateForm()
                                : null)
                            ->createOptionUsing(fn (array $data): int => self::createNonMember($data))
                            ->visible(fn (Get $get): bool => $get('registration_type') === RegistrationType::Acompanante->value)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label('Estado')
                            ->options(RegistrationStatus::class)
                            ->default(RegistrationStatus::Pendiente)
                            ->required()
                            ->columnSpan(1),
                        Select::make('payment_method')
                            ->label('Método de pago')
                            ->options(fn (): array => Association::paymentMethodOptions())
                            ->default(fn (): ?string => Association::defaultPaymentMethod())
                            ->required()
                            ->columnSpan(1),
                        DatePicker::make('paid_at')
                            ->label('Fecha de pago')
                            ->displayFormat('d/m/Y')
                            ->columnSpan(1),
                    ])
                    ->columnSpan(1)
                    ->columns(3),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
                Hidden::make('ui_mode_acompanante')
                    ->dehydrated(false)
                    ->default(false),
            ])->columns(2);
    }

    protected static function syncPricing(Get $get, Set $set): void
    {
        $eventId = $get('event_id');
        $registrationTypeValue = $get('registration_type');

        if (! filled($eventId) || ! filled($registrationTypeValue)) {
            return;
        }

        $event = Event::query()->find((int) $eventId);
        $registrationType = $registrationTypeValue instanceof RegistrationType
            ? $registrationTypeValue
            : RegistrationType::tryFrom((string) $registrationTypeValue);

        if (! $event || ! $registrationType) {
            return;
        }

        $pricing = $event->priceFor($registrationType);

        $set('amount', $pricing['amount']);
        $set('surcharge_amount', $pricing['surcharge_amount']);
        self::syncTotalAmount($get, $set);
    }

    protected static function syncEventContext(Get $get, Set $set): void
    {
        $event = self::currentEvent($get);

        if (! $event) {
            $set('registration_sequence', null);
            $set('registration_number', null);
            $set('amount', null);
            $set('surcharge_amount', 0);
            self::syncTotalAmount($get, $set);

            return;
        }

        $nextSequence = (int) $event->last_registration_sequence + 1;
        $set('registration_sequence', $nextSequence);
        $set('registration_number', sprintf('%s-%04d', $event->event_code, $nextSequence));

        if (! filled($get('registration_type'))) {
            $set('amount', (float) $event->base_price);
            $set('surcharge_amount', 0);
            self::syncTotalAmount($get, $set);
        }
    }

    protected static function syncTotalAmount(Get $get, Set $set): void
    {
        if (! filled($get('event_id')) && ! filled($get('amount')) && ! filled($get('surcharge_amount'))) {
            $set('total_amount', null);

            return;
        }

        $set(
            'total_amount',
            number_format((float) $get('amount') + (float) $get('surcharge_amount'), 2, '.', ''),
        );
    }

    protected static function syncParticipantState(Get $get, Set $set): void
    {
        $type = self::currentType($get);

        if (! $type) {
            $set('member_id', null);
            $set('sponsor_member_id', null);
            $set('ui_mode_acompanante', false);

            return;
        }

        $set('ui_mode_acompanante', $type === RegistrationType::Acompanante);

        $memberOptions = self::memberOptions($get);
        $sponsorOptions = self::sponsorOptions($get);

        if (! array_key_exists((string) $get('member_id'), $memberOptions)) {
            $set('member_id', array_key_first($memberOptions));
        }

        if ($type === RegistrationType::Acompanante) {
            if (! array_key_exists((string) $get('sponsor_member_id'), $sponsorOptions)) {
                $set('sponsor_member_id', array_key_first($sponsorOptions));
            }

            return;
        }

        $set('sponsor_member_id', null);
    }

    protected static function currentEvent(Get $get): ?Event
    {
        $eventId = $get('event_id');

        if (! filled($eventId)) {
            return null;
        }

        return Event::query()->find((int) $eventId);
    }

    protected static function currentType(Get $get): ?RegistrationType
    {
        $value = $get('registration_type');

        if ($value instanceof RegistrationType) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return RegistrationType::tryFrom($value);
    }

    public static function canCreateMemberOption(?RegistrationType $type): bool
    {
        return $type === RegistrationType::NoSocio;
    }

    public static function canCreateSponsorOption(?RegistrationType $type): bool
    {
        return $type === RegistrationType::Acompanante;
    }

    protected static function participantLabel(Get $get): string
    {
        return self::currentType($get) === RegistrationType::Acompanante
            ? 'Socio patrocinador'
            : 'Participante';
    }

    /**
     * @return array<string, string>
     */
    protected static function memberOptions(Get $get): array
    {
        $type = self::currentType($get);

        if (! $type) {
            return [];
        }

        $query = Member::query()->orderBy('last_name')->orderBy('first_name');

        if ($type === RegistrationType::Socio) {
            $query
                ->where('member_type', MemberType::Socio->value)
                ->whereIn('status', [MemberStatus::Activo->value, MemberStatus::Pendiente->value]);
        } elseif ($type === RegistrationType::NoSocio) {
            $query->where(function ($subQuery): void {
                $subQuery
                    ->where('member_type', MemberType::NoSocio->value)
                    ->orWhere(function ($innerQuery): void {
                        $innerQuery
                            ->where('member_type', MemberType::Socio->value)
                            ->whereIn('status', [MemberStatus::Pendiente->value, MemberStatus::Baja->value]);
                    });
            });
        } elseif ($type === RegistrationType::Acompanante) {
            $query
                ->where('member_type', MemberType::Socio->value)
                ->where('status', MemberStatus::Activo->value);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Member $member): array => [
                (string) $member->id => $type === RegistrationType::Socio
                    ? trim("{$member->member_number_formatted} · {$member->full_name}")
                    : $member->full_name,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function sponsorOptions(Get $get): array
    {
        if (self::currentType($get) !== RegistrationType::Acompanante) {
            return [];
        }

        return Member::query()
            ->where(function ($query): void {
                $query
                    ->where('member_type', MemberType::NoSocio->value)
                    ->orWhere(function ($innerQuery): void {
                        $innerQuery
                            ->where('member_type', MemberType::Socio->value)
                            ->where('status', '!=', MemberStatus::Activo->value);
                    });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn (Member $member): array => [
                (string) $member->id => $member->full_name,
            ])
            ->all();
    }

    protected static function notifyPendingMemberForNoSocio(Get $get): void
    {
        if (self::currentType($get) !== RegistrationType::NoSocio) {
            return;
        }

        $memberId = $get('member_id');

        if (! filled($memberId)) {
            return;
        }

        $member = Member::query()->find((int) $memberId);

        if (! $member) {
            return;
        }

        if ($member->member_type !== MemberType::Socio || $member->status !== MemberStatus::Pendiente) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Participante no válido para No Socio')
            ->body('El participante consta como socio pendiente. Deberá darse de baja o continuar con el pago de la cuota.')
            ->send();
    }

    protected static function notifyPendingCompanionForAcompanante(Get $get): void
    {
        if (self::currentType($get) !== RegistrationType::Acompanante) {
            return;
        }

        $companionId = $get('sponsor_member_id');

        if (! filled($companionId)) {
            return;
        }

        $member = Member::query()->find((int) $companionId);

        if (! $member) {
            return;
        }

        if ($member->member_type !== MemberType::Socio || $member->status !== MemberStatus::Pendiente) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Acompañante en estado pendiente')
            ->body('El acompañante consta como socio pendiente de pago de cuota y pagará como acompañante.')
            ->send();
    }

    /**
     * @return array<int, mixed>
     */
    protected static function nonMemberCreateForm(): array
    {
        return [
            Section::make('Datos del no socio')
                ->schema([
                    TextInput::make('first_name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('last_name')
                        ->label('Apellidos')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(4),
                    TextInput::make('dni')
                        ->label('DNI')
                        ->maxLength(30)
                        ->columnSpan(3),
                    Select::make('gender')
                        ->label('Género')
                        ->options(Gender::class)
                        ->default(Gender::NoDefinido)
                        ->required()
                        ->columnSpan(3),
                ])
                ->columns(6),
            Section::make('Datos de contacto')
                ->schema([
                    TextInput::make('address')
                        ->label('Dirección')
                        ->maxLength(255)
                        ->columnSpan(4),
                    TextInput::make('postal_code')
                        ->label('C.P.')
                        ->maxLength(20)
                        ->columnSpan(1),
                    TextInput::make('city')
                        ->label('Ciudad')
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('province')
                        ->label('Provincia')
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('phone')
                        ->label('Teléfono')
                        ->maxLength(30)
                        ->columnSpan(2),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255)
                        ->columnSpan(3),
                ])
                ->columns(7),
            Hidden::make('member_type')
                ->default(MemberType::NoSocio->value),
        ];
    }

    protected static function createNonMember(array $data): int
    {
        $member = Member::query()->create([
            'first_name' => (string) ($data['first_name'] ?? ''),
            'last_name' => (string) ($data['last_name'] ?? ''),
            'dni' => filled($data['dni'] ?? null) ? (string) $data['dni'] : null,
            'member_number' => null,
            'email' => filled($data['email'] ?? null) ? (string) $data['email'] : null,
            'phone' => filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
            'address' => filled($data['address'] ?? null) ? (string) $data['address'] : null,
            'city' => filled($data['city'] ?? null) ? (string) $data['city'] : null,
            'province' => filled($data['province'] ?? null) ? (string) $data['province'] : null,
            'postal_code' => filled($data['postal_code'] ?? null) ? (string) $data['postal_code'] : null,
            'gender' => $data['gender'] ?? Gender::NoDefinido->value,
            'member_type' => MemberType::NoSocio->value,
            'status' => MemberStatus::Pendiente->value,
        ]);

        return $member->id;
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        return Association::paymentMethodOptions();
    }

    public static function defaultPaymentMethod(): ?string
    {
        return Association::defaultPaymentMethod();
    }
}
