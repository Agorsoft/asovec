<?php

namespace App\Filament\Resources\Associations\Schemas;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\Mailer\Transport;

class AssociationForm
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
                                        TextInput::make('name')->label('Nombre')->required()->maxLength(255)->columnSpan(4),
                                        TextInput::make('tax_id')->label('NIF / CIF')->maxLength(255)->columnSpan(2),
                                        TextInput::make('address')->label('Dirección')->maxLength(255)->columnSpan(4),
                                        TextInput::make('postal_code')->label('Código postal')->maxLength(10)->columnSpan(2),
                                        TextInput::make('city')->label('Localidad')->maxLength(255)->columnSpan(2),
                                        TextInput::make('province')->label('Provincia')->maxLength(255)->columnSpan(2),
                                        TextInput::make('country')->label('País')->required()->maxLength(255)->default('España')->columnSpan(2),
                                        TextInput::make('email')->label('Correo electrónico')->email()->maxLength(255)->columnSpan(2),
                                        TextInput::make('website')->label('Web')->url()->maxLength(255)->columnSpan(2),
                                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(255)->columnSpan(2),
                                        TextInput::make('registration_entity')->label('Entidad de registro')->maxLength(255)->columnSpan(5),
                                        TextInput::make('registration_number')->label('Nº registro')->maxLength(255)->columnSpan(1),
                                    ])
                                    ->columnSpan(3)
                                    ->columns(6),
                                Section::make('Logo y observaciones')
                                    ->schema([
                                        FileUpload::make('logo_path')
                                            ->label('Logo')
                                            ->image()
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                            ->maxSize(4096)
                                            ->directory('associations/logos')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->fetchFileInformation(false)
                                            ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file, Get $get): string => static::associationLogoFileName($file, $get))
                                            ->columnSpanFull(),
                                        Textarea::make('observations')->label('Observaciones')->rows(4)->columnSpanFull(),
                                    ])->columnSpan(2),
                            ])->columns(5),
                        Tab::make('Finanzas')
                            ->schema([
                                Section::make('Pagos')
                                    ->headerActions([
                                        Action::make('mandatoSepa')
                                            ->label('Ver mandato SEPA')
                                            ->icon('heroicon-o-printer')
                                            ->url(fn ($livewire): string => route('associations.sepa.print', $livewire->getRecord()))
                                            ->openUrlInNewTab()
                                            ->visible(fn (Get $get): bool => static::requiresBankData($get('payment_methods'))),
                                    ])
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                CheckboxList::make('payment_methods')
                                                    ->label('Metodos de pago')
                                                    ->options([
                                                        PaymentMethod::Efectivo->value => PaymentMethod::Efectivo->label(),
                                                        PaymentMethod::Transferencia->value => PaymentMethod::Transferencia->label(),
                                                        PaymentMethod::Bizum->value => PaymentMethod::Bizum->label(),
                                                        PaymentMethod::Tarjeta->value => PaymentMethod::Tarjeta->label(),
                                                        PaymentMethod::Domiciliacion->value => PaymentMethod::Domiciliacion->label(),
                                                    ])
                                                    ->columns(5)
                                                    ->live()
                                                    ->extraAttributes(['class' => 'mb-6'])
                                                    ->columnSpan(2),
                                                Select::make('payment_period')
                                                    ->label('Periodicidad de pago')
                                                    ->options([
                                                        PaymentPeriod::Anual->value => PaymentPeriod::Anual->label(),
                                                        PaymentPeriod::Semestral->value => PaymentPeriod::Semestral->label(),
                                                        PaymentPeriod::Trimestral->value => PaymentPeriod::Trimestral->label(),
                                                        PaymentPeriod::Mensual->value => PaymentPeriod::Mensual->label(),
                                                    ])
                                                    ->native(false)
                                                    ->columnSpan(1),
                                                TextInput::make('bank_name')->label('Nombre del banco')->maxLength(255)->columnSpan(1),
                                                TextInput::make('iban')->label('IBAN de la asociacion')->maxLength(24)->mask('aa99 9999 9999 9999 9999 9999')->copyable(copyMessage: 'Copiado!', copyMessageDuration: 1500)->columnSpan(1),
                                                TextInput::make('bic')->label('BIC de la asociacion')->maxLength(255)->columnSpan(1),
                                            ])
                                            ->columnSpanFull()
                                            ->columns(3),

                                        Section::make('Datos bancarios (solo para Domiciliacion)')
                                            ->visible(fn (Get $get): bool => static::requiresBankData($get('payment_methods')))
                                            ->schema([
                                                Grid::make(5)
                                                    ->schema([
                                                        TextInput::make('bank_branch')->label('Sucursal')->maxLength(255)->columnSpan(2),
                                                        TextInput::make('bank_address')->label('Dirección del banco')->maxLength(255)->columnSpan(2),
                                                        TextInput::make('bank_postal_code')->label('Código postal')->maxLength(255)->columnSpan(1),
                                                        TextInput::make('bank_city')->label('Localidad')->maxLength(255)->columnSpan(2),
                                                        TextInput::make('bank_province')->label('Provincia')->maxLength(255)->columnSpan(1),
                                                        TextInput::make('bank_country')->label('País')->maxLength(255)->columnSpan(1),
                                                        TextInput::make('creditor_identifier')->label('Identificador acreedor SEPA')->maxLength(255)->columnSpan(2),
                                                    ]),
                                            ])
                                            ->extraAttributes(['class' => 'mt-6']),
                                    ]),
                            ]),
                        Tab::make('Numeración')
                            ->schema([
                                Section::make('Datos de Asociación')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                TextInput::make('receipt_number_mask')
                                                    ->label('Mascara de recibo')
                                                    ->default('{Y:2}-{N:4}')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->regex('/^\{(YYYY|Y:\\d+)\}-\{(NNNN|N:\\d+)\}$/'),
                                                TextInput::make('last_member_number')
                                                    ->label('Ultimo numero de socio')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->required(),
                                                TextInput::make('last_receipt_number')
                                                    ->label('Ultimo numero de recibo')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->required(),
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
                                    ->headerActions([
                                        Action::make('testMail')
                                            ->label('Probar conexión')
                                            ->icon('heroicon-o-bolt')
                                            ->action(function (Get $get): void {
                                                $mailer = $get('mail_mailer') ?? '';
                                                $host = $get('mail_host') ?? '';
                                                $port = $get('mail_port') ?? '';
                                                $user = $get('mail_username') ?? '';
                                                $pass = $get('mail_password') ?? '';
                                                $encryption = $get('mail_encryption') ?? '';

                                                if ($mailer !== 'smtp') {
                                                    Notification::make()->danger()->title('No soportado')->body('Solo comprobación SMTP soportada en esta prueba.')->send();

                                                    return;
                                                }

                                                $scheme = $encryption === 'ssl' ? 'smtps' : 'smtp';
                                                $userEnc = rawurlencode((string) ($user ?? ''));
                                                $passEnc = rawurlencode((string) ($pass ?? ''));
                                                $dsn = sprintf('%s://%s:%s@%s:%s', $scheme, $userEnc, $passEnc, $host, $port);

                                                try {
                                                    $transport = Transport::fromDsn($dsn);
                                                    $transport->start();
                                                    Notification::make()->success()->title('Conexión SMTP OK')->send();
                                                } catch (\Throwable $e) {
                                                    Notification::make()->danger()->title('Error al conectar')->body($e->getMessage())->send();
                                                }
                                            }),
                                    ])
                                    ->schema([
                                        Checkbox::make('use_email')
                                            ->label('Usar servicio de correo')
                                            ->live()
                                            ->columnSpanFull(),

                                        Select::make('mail_mailer')
                                            ->label('Mailer')
                                            ->options([
                                                'smtp' => 'SMTP',
                                                'sendmail' => 'Sendmail',
                                                'mailgun' => 'Mailgun',
                                                'ses' => 'Amazon SES',
                                                'log' => 'Log',
                                            ])
                                            ->native(false)
                                            ->columnSpan(2)
                                            ->disabled(fn (Get $get): bool => ! $get('use_email')),

                                        TextInput::make('mail_host')->label('Host')->maxLength(255)->columnSpan(2)->disabled(fn (Get $get): bool => ! $get('use_email')),
                                        TextInput::make('mail_port')->label('Puerto')->numeric()->columnSpan(1)->disabled(fn (Get $get): bool => ! $get('use_email')),

                                        Select::make('mail_encryption')
                                            ->label('Cifrado')
                                            ->options([
                                                'tls' => 'TLS',
                                                'ssl' => 'SSL',
                                                'starttls' => 'STARTTLS',
                                                'none' => 'Ninguno',
                                            ])
                                            ->native(false)
                                            ->columnSpan(1)
                                            ->disabled(fn (Get $get): bool => ! $get('use_email')),

                                        TextInput::make('mail_username')->label('Usuario')->maxLength(255)->columnSpan(2)->disabled(fn (Get $get): bool => ! $get('use_email')),
                                        TextInput::make('mail_password')->label('Contraseña')->password()->columnSpan(2)->disabled(fn (Get $get): bool => ! $get('use_email')),

                                        TextInput::make('mail_from_address')->label('Remitente (dirección)')->email()->maxLength(255)->columnSpan(2)->disabled(fn (Get $get): bool => ! $get('use_email')),
                                        TextInput::make('mail_from_name')->label('Remitente (nombre)')->maxLength(255)->columnSpan(2)->disabled(fn (Get $get): bool => ! $get('use_email')),
                                    ])
                                    ->columns(4),
                            ]),
                    ]),
            ])->columns(1);

    }

    protected static function requiresBankData(mixed $methods): bool
    {
        $values = collect($methods ?? [])->map(fn (mixed $value): string => ($value instanceof \BackedEnum) ? $value->value : (string) $value
        );

        return $values
            ->intersect([
                PaymentMethod::Domiciliacion->value,
            ])
            ->isNotEmpty();
    }

    protected static function associationLogoFileName(TemporaryUploadedFile $file, Get $get): string
    {
        $associationName = Str::slug((string) $get('name'));
        $baseName = $associationName !== '' ? $associationName : 'asociacion';
        $baseName = Str::substr($baseName, 0, 40);

        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');

        return "{$baseName}-".now()->format('YmdHis').'-'.Str::lower(Str::random(4)).".{$extension}";
    }
}
