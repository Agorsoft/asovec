<?php

namespace App\Filament\Resources\Quotas\Tables;

use App\Enums\PaymentMethod;
use App\Enums\PdfEngine;
use App\Enums\QuotaStatus;
use App\Mail\QuotaMemberReceiptMail;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Models\Quota;
use App\Services\PdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
}
