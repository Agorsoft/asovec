<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\SepaMandateStatus;
use App\Enums\SepaSequenceType;
use App\Models\Association;
use App\Models\Member;
use App\Models\QuotaType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos personales')
                    ->schema([
                        TextInput::make('first_name')->label('Nombre')->required()->validationMessages(['required' => 'El nombre es obligatorio.'])->columnSpan(2),
                        TextInput::make('last_name')->label('Apellidos')->required()->validationMessages(['required' => 'Los apellidos son obligatorios.'])->columnSpan(3),
                        TextInput::make('member_number')
                            ->label('Nº de socio')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Ya existe un socio con ese número. Cambie el número antes de guardar.',
                            ])
                            // ->helperText('Se asigna automáticamente al activar si está vacío.')
                            ->placeholder('Se asigna al activar')
                            ->columnSpan(1),
                        TextInput::make('dni')->label('DNI/NIF')->unique(ignoreRecord: true)->validationMessages(['unique' => 'Ya existe un socio con ese DNI/NIF. Cambie el DNI/NIF antes de guardar.'])->columnSpan(2),
                        DatePicker::make('birth_date')->label('Fecha de nacimiento')->displayFormat('d/m/Y')->columnSpan(2),
                        Select::make('gender')->label('Genero')->options(Gender::class)->default(Gender::NoDefinido->value)->native(false)->required()->validationMessages(['required' => 'El género es obligatorio.'])->columnSpan(2)->placeholder('Seleccione'),
                        Section::make('')
                            ->schema([
                                TextInput::make('address')->label('Dirección')->columnSpan(6),
                                TextInput::make('postal_code')->label('C.P.')->columnSpan(2),
                                TextInput::make('city')->label('Localidad')->columnSpan(4),
                                TextInput::make('province')->label('Provincia')->columnSpan(3),
                                TextInput::make('phone')->label('Teléfono')->tel()->live(onBlur: true)->columnSpan(3),
                                TextInput::make('email')->label('Correo electrónico')->email()->live(onBlur: true)->columnSpan(6),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                        Section::make('Comunicaciones')
                            ->schema([
                                Checkbox::make('whatsapp_opt_in')
                                    ->label('WhatsApp')
                                    ->disabled(fn (Get $get): bool => ! filled($get('phone')))
                                    ->helperText(fn (Get $get): ?string => filled($get('phone')) ? null : 'Requiere teléfono')
                                    ->columnSpan(4),
                                Checkbox::make('sms_opt_in')
                                    ->label('Mensaje SMS')
                                    ->disabled(fn (Get $get): bool => ! filled($get('phone')))
                                    ->helperText(fn (Get $get): ?string => filled($get('phone')) ? null : 'Requiere teléfono')
                                    ->columnSpan(4),
                                Checkbox::make('email_opt_in')
                                    ->label('Correo electrónico')
                                    ->disabled(fn (Get $get): bool => ! filled($get('email')))
                                    ->helperText(fn (Get $get): ?string => filled($get('email')) ? null : 'Requiere correo electrónico')
                                    ->columnSpan(4),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                        Section::make('Datos Bancarios')
                            ->visible(fn (Get $get): bool => static::requiresBankData($get('payment_method_id')))
                            ->schema([
                                TextInput::make('bank_account_holder')->label('Titular de la cuenta')->maxLength(255)->columnSpan(3),
                                TextInput::make('bank_iban')->label('IBAN')->maxLength(24)->mask('aa99 9999 9999 9999 9999 9999')->copyable(copyMessage: 'Copiado!', copyMessageDuration: 1500)->columnSpan(3),
                                TextInput::make('bank_bic')->label('BIC')->maxLength(11)->columnSpan(2),
                                TextInput::make('sepa_mandate_reference')->label('Referencia mandato')->maxLength(255)->columnSpan(2),
                                TextInput::make('phone')->label('Teléfono')->tel()->columnSpan(3),
                                TextInput::make('email')->label('Correo electrónico')->email()->columnSpan(6),
                            ])->columns(12)->columnSpanFull()->extraAttributes(['class' => 'mt-4']),
                        Section::make('Datos Bancarios')
                            ->visible(fn (Get $get): bool => static::requiresBankData($get('payment_method_id')))
                            ->schema([
                                TextInput::make('bank_account_holder')->label('Titular de la cuenta')->maxLength(255)->columnSpan(3),
                                TextInput::make('bank_iban')->label('IBAN')->maxLength(24)->mask('aa99 9999 9999 9999 9999 9999')->copyable(copyMessage: 'Copiado!', copyMessageDuration: 1500)->columnSpan(3),
                                TextInput::make('bank_bic')->label('BIC')->maxLength(11)->columnSpan(2),
                                TextInput::make('sepa_mandate_reference')->label('Referencia mandato')->maxLength(255)->columnSpan(2),
                                Select::make('sepa_mandate_status')->label('Estado mandato')->options(SepaMandateStatus::class)->native(false)->columnSpan(2)->placeholder('Seleccione'),
                                Select::make('sepa_sequence_type')->label('Secuencia SEPA')->options(SepaSequenceType::class)->native(false)->columnSpan(2)->placeholder('Seleccione'),
                                DatePicker::make('sepa_mandate_signed_at')->label('Firma mandato')->displayFormat('d/m/Y')->columnSpan(2),
                            ])->columnSpanFull()->columns(8)->extraAttributes(['class' => 'mt-4']),
                    ])->columns(6)->columnSpan(2),
                Section::make('Asociado')
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Foto')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                            ->maxSize(4096)
                            ->directory('members/photos')
                            ->disk('public')
                            ->visibility('public')
                            ->fetchFileInformation(false)
                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => static::memberPhotoFileName($file, $get))
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'mb-4']),
                        DatePicker::make('joined_at')->label('Fecha de alta')->displayFormat('d/m/Y')->columnSpan(1),
                        DatePicker::make('left_at')
                            ->label('Fecha de baja')
                            ->columnSpan(1)
                            ->displayFormat('d/m/Y')
                            ->reactive()
                            ->disabled(fn ($get): bool => $get('status') !== MemberStatus::Baja->value)
                            ->required(fn ($get): bool => $get('status') === MemberStatus::Baja->value)
                            ->validationMessages(['required' => 'La fecha de baja es obligatoria cuando el estado es Baja.']),
                        Select::make('payment_method_id')
                            ->label('Método de pago')
                            ->options(fn (): array => Association::paymentMethodOptions())
                            ->default(fn (): ?string => Association::defaultPaymentMethod())
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->required()
                            ->validationMessages(['required' => 'El método de pago es obligatorio.'])
                            ->columnSpan(1)
                            ->placeholder('Seleccione'),
                        Select::make('status')
                            ->label('Estado')
                            ->options(function (?Member $record): array {
                                $options = collect(MemberStatus::cases())
                                    ->mapWithKeys(fn (MemberStatus $s) => [$s->value => $s->label()]);

                                if ($record && $record->status === MemberStatus::Activo && static::memberHasCurrentPaidQuota($record)) {
                                    $options->forget(MemberStatus::Pendiente->value);
                                }

                                return $options->all();
                            })
                            ->default(MemberStatus::Pendiente->value)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get, ?Member $record): void {
                                if ($state === MemberStatus::Baja->value) {
                                    if (! filled($get('left_at'))) {
                                        $set('left_at', Carbon::now()->toDateString());
                                    }

                                    return;
                                }

                                if ($state === MemberStatus::Activo->value) {
                                    $set('left_at', null);

                                    if ($record && static::memberHasCurrentPaidQuota($record)) {
                                        $set('quota_id', null);

                                        return;
                                    }

                                    if (! static::hasAvailableQuotaTypes()) {
                                        Notification::make()
                                            ->warning()
                                            ->title('No hay tipos de cuota disponibles')
                                            ->body('Para activar socios, primero debe crear al menos un tipo de cuota.')
                                            ->send();
                                    }
                                }
                            })
                            ->placeholder('Seleccione')
                            ->required()
                            ->validationMessages(['required' => 'El estado es obligatorio.'])
                            ->columnSpan(1),
                        Select::make('quota_id')
                            ->label('Tipo de cuota')
                            ->options(fn (): array => static::availableQuotaTypes())
                            ->disabled(fn (): bool => ! static::hasAvailableQuotaTypes())
                            ->visible(fn (?Member $record, Get $get): bool => in_array($get('status'), [MemberStatus::Activo->value, MemberStatus::Pendiente->value])
                                && ! ($record && static::memberHasCurrentPaidQuota($record)))
                            ->helperText(fn (Get $get): ?string => static::hasAvailableQuotaTypes()
                                ? ($get('status') === MemberStatus::Activo->value
                                    ? 'Seleccione el tipo de cuota que se marcará como pagada al activar.'
                                    : 'Seleccione el tipo de cuota que se creará como pendiente de pago.')
                                : 'No existen tipos de cuota disponibles. Debe crearlos antes.')
                            ->required(fn (?Member $record, Get $get): bool => static::hasAvailableQuotaTypes()
                                && in_array($get('status'), [MemberStatus::Activo->value, MemberStatus::Pendiente->value])
                                && ($record === null || ($get('status') === MemberStatus::Activo->value && ! static::memberHasCurrentPaidQuota($record))))
                            ->validationMessages(['required' => 'Debe asignar un tipo de cuota.'])
                            ->dehydrated()
                            ->columnSpanFull(),
                        Textarea::make('notes')->label('Notas')->columnSpanFull(),
                    ])->columns(2)->columnSpan(1),
            ])->columns(3);
    }

    /**
     * @return array<string, string>
     */
    protected static function availablePaymentMethods(): array
    {
        return Association::paymentMethodOptions();
    }

    public static function defaultPaymentMethod(): ?string
    {
        return Association::defaultPaymentMethod();
    }

    /**
     * @return array<int, string>
     */
    protected static function availableQuotaTypes(): array
    {
        return QuotaType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function hasAvailableQuotaTypes(): bool
    {
        return QuotaType::query()->exists();
    }

    protected static function memberHasCurrentPaidQuota(Member $member): bool
    {
        return $member->hasCurrentPaidQuota();
    }

    protected static function requiresBankData(mixed $paymentMethod): bool
    {
        $value = ($paymentMethod instanceof \BackedEnum) ? $paymentMethod->value : (string) $paymentMethod;

        return $value === PaymentMethod::Domiciliacion->value;
    }

    protected static function memberPhotoFileName(TemporaryUploadedFile $file, Get $get): string
    {
        $firstName = Str::slug((string) $get('first_name'));
        $lastName = Str::slug((string) $get('last_name'));

        $baseName = trim("{$firstName}-{$lastName}", '-');
        $baseName = $baseName !== '' ? $baseName : 'socio';
        $baseName = Str::substr($baseName, 0, 40);

        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');

        return "{$baseName}-".now()->format('YmdHis').'-'.Str::lower(Str::random(4)).".{$extension}";
    }
}
