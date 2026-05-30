<?php

namespace App\Filament\Resources\Quotas\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PdfEngine;
use App\Enums\QuotaStatus;
use App\Jobs\SendQuotaNoticesJob;
use App\Mail\QuotaMemberReceiptMail;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Models\Quota;
use App\Services\PdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('exportQuotas')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        CheckboxList::make('optional_columns')
                            ->label('Columnas opcionales')
                            ->options([
                                'due_date' => 'Vencimiento',
                                'payment_method' => 'Método de pago',
                                'quota_type_name' => 'Tipo de cuota',
                                'receipt_number' => 'Nº recibo',
                            ])
                            ->columns(2),
                    ])
                    ->modalHeading('Exportar cuotas')
                    ->modalSubmitActionLabel('Descargar CSV')
                    ->action(function (array $data, $livewire): StreamedResponse {
                        $selectedOptionalColumns = collect($data['optional_columns'] ?? [])
                            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                            ->values()
                            ->all();

                        $headers = [
                            'Nº socio',
                            'Socio',
                            'Desde',
                            'Hasta',
                            'Importe',
                            'Estado',
                            'Pago',
                        ];

                        foreach ($selectedOptionalColumns as $column) {
                            $headers[] = match ($column) {
                                'due_date' => 'Vencimiento',
                                'payment_method' => 'Método de pago',
                                'quota_type_name' => 'Tipo de cuota',
                                'receipt_number' => 'Nº recibo',
                                default => $column,
                            };
                        }

                        $quotas = $livewire->getTableQueryForExport()
                            ->with(['member', 'quotaType'])
                            ->get();

                        return response()->streamDownload(function () use ($headers, $quotas, $selectedOptionalColumns): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            // UTF-8 BOM for Excel compatibility with accents.
                            fwrite($stream, "\xEF\xBB\xBF");

                            fputcsv($stream, $headers, ';');

                            /** @var Quota $quota */
                            foreach ($quotas as $quota) {
                                $row = [
                                    $quota->member?->member_number,
                                    trim((string) ($quota->member?->last_name.', '.$quota->member?->first_name)),
                                    $quota->period_start?->format('d/m/Y'),
                                    $quota->period_end?->format('d/m/Y'),
                                    number_format((float) $quota->amount, 2, '.', ''),
                                    $quota->status?->value,
                                    $quota->paid_at?->format('d/m/Y'),
                                ];

                                foreach ($selectedOptionalColumns as $column) {
                                    $row[] = match ($column) {
                                        'due_date' => $quota->due_date?->format('d/m/Y'),
                                        'payment_method' => $quota->payment_method,
                                        'quota_type_name' => $quota->quotaType?->name,
                                        'receipt_number' => $quota->receipt_number,
                                        default => null,
                                    };
                                }

                                fputcsv($stream, $row, ';');
                            }

                            fclose($stream);
                        }, 'cuotas_export_'.now()->format('Ymd_His').'.csv', [
                            'Content-Type' => 'text/csv; charset=utf-8',
                        ]);
                    }),
                ActionGroup::make([
                    Action::make('sendPendingNotices')
                        ->label('Avisos: cuotas pendientes')
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->visible(fn (): bool => (bool) Association::query()->value('use_email'))
                        ->form([
                            CheckboxList::make('quota_ids')
                                ->label('Seleccionar destinatarios')
                                ->options(fn (): array => Quota::where('status', QuotaStatus::Pendiente->value)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->with('member')
                                    ->get()
                                    ->mapWithKeys(fn (Quota $q) => [$q->id => trim("{$q->member?->last_name}, {$q->member?->first_name}")." <{$q->member?->email}>"])
                                    ->toArray()),
                            Checkbox::make('all')->label('Seleccionar todos'),
                        ])
                        ->modalHeading('Enviar avisos de cuota pendiente')
                        ->modalWidth('xl')
                        ->action(function (array $data) {
                            $all = (bool) ($data['all'] ?? false);
                            $selected = $data['quota_ids'] ?? [];

                            if ($all) {
                                $count = Quota::where('status', QuotaStatus::Pendiente->value)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->count();
                            } else {
                                $count = Quota::whereIn('id', $selected)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->where('status', QuotaStatus::Pendiente->value)
                                    ->count();
                            }

                            if ($count > 20) {
                                SendQuotaNoticesJob::dispatch($all ? null : $selected, QuotaStatus::Pendiente->value);

                                Notification::make()->success()->title('Envio en background')->body("El envío de {$count} emails se ha encolado y se procesará automáticamente")->send();

                                return null;
                            }

                            static::sendBulkNotices(QuotaStatus::Pendiente, $all ? null : $selected);

                            return null;
                        }),
                    Action::make('sendExpiredNotices')
                        ->label('Avisos: cuotas vencidas')
                        ->icon('heroicon-o-exclamation-circle')
                        ->color('danger')
                        ->visible(fn (): bool => (bool) Association::query()->value('use_email'))
                        ->form([
                            CheckboxList::make('quota_ids')
                                ->label('Seleccionar destinatarios')
                                ->options(fn (): array => Quota::where('status', QuotaStatus::Vencida->value)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->with('member')
                                    ->get()
                                    ->mapWithKeys(fn (Quota $q) => [$q->id => trim("{$q->member?->last_name}, {$q->member?->first_name}")." <{$q->member?->email}>"])
                                    ->toArray()),
                            Checkbox::make('all')->label('Seleccionar todos'),
                        ])
                        ->modalHeading('Enviar avisos de cuota vencida')
                        ->modalWidth('xl')
                        ->action(function (array $data) {
                            $all = (bool) ($data['all'] ?? false);
                            $selected = $data['quota_ids'] ?? [];

                            if ($all) {
                                $count = Quota::where('status', QuotaStatus::Vencida->value)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->count();
                            } else {
                                $count = Quota::whereIn('id', $selected)
                                    ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                                    ->where('status', QuotaStatus::Vencida->value)
                                    ->count();
                            }

                            if ($count > 20) {
                                SendQuotaNoticesJob::dispatch($all ? null : $selected, QuotaStatus::Vencida->value);

                                Notification::make()->success()->title('Envio en background')->body("El envío de {$count} emails se ha encolado y se procesará automáticamente")->send();

                                return null;
                            }

                            static::sendBulkNotices(QuotaStatus::Vencida, $all ? null : $selected);

                            return null;
                        }),
                ])
                    ->label('Enviar avisos')
                    ->icon('heroicon-o-envelope-open')
                    ->color('info')
                    ->button()
                    ->visible(fn (): bool => (bool) Association::query()->value('use_email')),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                QuotaStatus::Pendiente->value,
                QuotaStatus::Pagada->value,
                QuotaStatus::Vencida->value,
            ]))
            ->columns([
                TextColumn::make('member.member_number')
                    ->label('Nº socio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('member.last_name')
                    ->label('Socio')
                    ->state(fn (Quota $record): string => trim("{$record->member?->last_name}, {$record->member?->first_name}"))
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('quotaType.name')
                    ->label('Tipo de cuota')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('period_start')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    // ->state(fn (Quota $record): string => $record->effectiveStatus()->value)
                    ->badge()
                    ->color(fn (QuotaStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label('Pago')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        QuotaStatus::Pendiente->value => QuotaStatus::Pendiente->Label(),
                        QuotaStatus::Pagada->value => QuotaStatus::Pagada->Label(),
                        QuotaStatus::Vencida->value => QuotaStatus::Vencida->Label(),
                    ]),
                SelectFilter::make('quota_type_id')
                    ->label('Tipo de cuota')
                    ->relationship('quotaType', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('markAsPaid')
                        ->label('Pagar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Pagada)
                        ->schema([
                            Select::make('payment_method')
                                ->label('Método de pago')
                                ->options(fn (): array => Association::paymentMethodOptions())
                                ->default(fn (): ?string => Association::defaultPaymentMethod())
                                ->native(false)
                                ->required(),
                            Textarea::make('notes')
                                ->label('Notas')
                                ->rows(3),
                        ])
                        ->action(function (Quota $record, array $data): void {
                            $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                                ? $data['payment_method']->value
                                : (string) $data['payment_method'];

                            $record->markAsPaid(
                                $paymentMethod,
                                filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                            );

                            Notification::make()
                                ->success()
                                ->title('Cuota marcada como pagada')
                                ->actions([
                                    Action::make('printReceipt')
                                        ->label('Imprimir recibo A4')
                                        ->url(route('quotas.receipt.show', ['quota' => $record->id, 'type' => 'full']))
                                        ->openUrlInNewTab()
                                        ->button(),
                                ])
                                ->send();
                        }),
                    Action::make('cancel')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Cancelada)
                        ->requiresConfirmation()
                        ->action(function (Quota $record): void {
                            $wasPaid = $record->status === QuotaStatus::Pagada;

                            $record->update([
                                'status' => QuotaStatus::Cancelada,
                            ]);

                            $notification = Notification::make()
                                ->success()
                                ->title('Cuota cancelada');

                            if ($wasPaid) {
                                $notification->actions([
                                    Action::make('printRefund')
                                        ->label('Imprimir nota de devolución')
                                        ->url(route('quotas.receipt.show', ['quota' => $record->id, 'type' => 'refund']))
                                        ->openUrlInNewTab()
                                        ->button(),
                                ]);
                            }

                            $notification->send();
                        }),
                    ViewAction::make()
                        ->label('Ver'),
                    EditAction::make()
                        ->label('Editar'),
                    Action::make('separator')
                        ->label('—')
                        ->disabled()
                        ->color('gray'),
                    Action::make('printRefundReceipt')
                        ->label('Nota de devolución')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => $record->status === QuotaStatus::Cancelada)
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'refund',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptFull')
                        ->label('Recibo completo')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptMember')
                        ->label('Recibo de socio')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record) => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'member',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printPendingNotice')
                        ->label('Aviso de cuota')
                        ->icon('heroicon-o-bell-alert')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => in_array($record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true))
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'pending_notice',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('sendEmail')
                        ->label('Enviar')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(function (Quota $record): bool {
                            static $useEmail = null;
                            if ($useEmail === null) {
                                $useEmail = (bool) Association::query()->value('use_email');
                            }

                            return $useEmail
                                && filled($record->member?->email)
                                && (bool) ($record->member?->email_opt_in)
                                && in_array($record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida, QuotaStatus::Pagada], true);
                        })
                        ->requiresConfirmation()
                        ->modalHeading(fn (?Quota $record): string => $record
                            ? 'Enviar por email a '.$record->member?->email
                            : 'Enviar por email')
                        ->modalDescription(fn (?Quota $record): string => $record && in_array($record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true)
                            ? 'Se enviará el aviso de cuota pendiente al socio.'
                            : 'Se enviará la copia del recibo al socio.')
                        ->action(function (Quota $record): void {
                            $association = Association::firstOrFail();

                            Config::set('mail.mailers.assoc_dynamic', [
                                'transport' => $association->mail_mailer ?? 'smtp',
                                'host' => $association->mail_host,
                                'port' => $association->mail_port,
                                'encryption' => $association->mail_encryption,
                                'username' => $association->mail_username,
                                'password' => $association->mail_password,
                                'from' => [
                                    'address' => (string) ($association->mail_from_address ?? $association->email),
                                    'name' => (string) ($association->mail_from_name ?? $association->name),
                                ],
                            ]);
                            Mail::purge('assoc_dynamic');

                            $pdfService = app(PdfService::class);
                            $memberEmail = (string) $record->member?->email;
                            $memberName = $record->member?->full_name ?? '-';

                            if (in_array($record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true)) {
                                $noticeDate = now()->format('d/m/Y');
                                $pdfBytes = $pdfService->renderView('documents.quotas.QuotaPendingNoticeA5', [
                                    'quota' => $record,
                                    'association' => $association,
                                    'noticeDate' => $noticeDate,
                                ], PdfEngine::Dompdf);

                                $mailable = new QuotaPendingNoticeMail($record, $pdfBytes, $noticeDate);
                            } else {
                                $pdfBytes = $pdfService->renderView('documents.quotas.QuotaReceiptA5', [
                                    'quota' => $record,
                                    'association' => $association,
                                    'receiptType' => 'member',
                                ], PdfEngine::Dompdf);

                                $mailable = new QuotaMemberReceiptMail($record, $pdfBytes);
                            }

                            $mailer = Mail::mailer('assoc_dynamic');
                            $mailer->to($memberEmail, $memberName)->send($mailable);

                            Notification::make()
                                ->success()
                                ->title('Email enviado correctamente')
                                ->body('Se ha enviado el email a '.$memberEmail)
                                ->send();
                        }),
                ]),
            ]);
    }

    private static function sendBulkNotices(QuotaStatus $status, ?array $quotaIds = null): void
    {
        $association = Association::firstOrFail();

        Config::set('mail.mailers.assoc_dynamic', [
            'transport' => $association->mail_mailer ?? 'smtp',
            'host' => $association->mail_host,
            'port' => $association->mail_port,
            'encryption' => $association->mail_encryption,
            'username' => $association->mail_username,
            'password' => $association->mail_password,
            'from' => [
                'address' => (string) ($association->mail_from_address ?? $association->email),
                'name' => (string) ($association->mail_from_name ?? $association->name),
            ],
        ]);
        Mail::purge('assoc_dynamic');

        if ($quotaIds === null) {
            $quotas = Quota::where('status', $status->value)
                ->whereHas('member', fn (Builder $q) => $q->whereNotNull('email')->where('email_opt_in', true))
                ->with(['member', 'quotaType'])
                ->get();
        } else {
            $quotas = Quota::whereIn('id', $quotaIds)
                ->with(['member', 'quotaType'])
                ->get()
                ->filter(fn (Quota $q) => filled($q->member?->email) && (bool) $q->member?->email_opt_in && $q->status->value === $status->value)
                ->values();
        }

        if ($quotas->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Sin destinatarios')
                ->body('No hay socios con cuota '.mb_strtolower($status->value).' y email configurado.')
                ->send();

            return;
        }

        $pdfService = app(PdfService::class);
        $noticeDate = now()->format('d/m/Y');
        $sent = 0;
        $errors = 0;

        foreach ($quotas as $quota) {
            try {
                $pdfBytes = $pdfService->renderView('documents.quotas.QuotaPendingNoticeA5', [
                    'quota' => $quota,
                    'association' => $association,
                    'noticeDate' => $noticeDate,
                ], PdfEngine::Dompdf);

                Mail::mailer('assoc_dynamic')
                    ->to((string) $quota->member->email, $quota->member->full_name ?? '-')
                    ->send(new QuotaPendingNoticeMail($quota, $pdfBytes, $noticeDate));

                $sent++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        if ($errors > 0) {
            Notification::make()
                ->warning()
                ->title("Enviados {$sent} de ".($sent + $errors))
                ->body("{$errors} envío(s) no pudieron completarse.")
                ->send();
        } else {
            Notification::make()
                ->success()
                ->title("Avisos enviados: {$sent}")
                ->body('Se han enviado los avisos de cuota '.mb_strtolower($status->value).'.')
                ->send();
        }
    }
}
