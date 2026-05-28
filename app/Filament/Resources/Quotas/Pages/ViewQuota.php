<?php

namespace App\Filament\Resources\Quotas\Pages;

use App\Enums\PaymentMethod;
use App\Enums\PdfEngine;
use App\Enums\QuotaStatus;
use App\Filament\Resources\Quotas\QuotaResource;
use App\Mail\QuotaMemberReceiptMail;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Services\PdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class ViewQuota extends ViewRecord
{
    protected static string $resource = QuotaResource::class;

    protected static ?string $title = 'Ver cuota';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => QuotaResource::getUrl('index'))
                ->color('gray'),
            ActionGroup::make([
                Action::make('printReceiptFull')
                    ->label('Recibo completo')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (): bool => ! in_array($this->record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'full',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptAssociation')
                    ->label('Recibo de asociación')
                    ->icon('heroicon-o-building-office-2')
                    ->color('gray')
                    ->visible(fn (): bool => ! in_array($this->record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'association',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printReceiptMember')
                    ->label('Recibo de socio')
                    ->icon('heroicon-o-user')
                    ->color('gray')
                    ->visible(fn (): bool => ! in_array($this->record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'member',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printRefundReceipt')
                    ->label('Nota de devolución')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->status === QuotaStatus::Cancelada)
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'refund',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('printPendingNotice')
                    ->label('Aviso de cuota')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->visible(fn (): bool => in_array($this->record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true))
                    ->url(fn () => route('quotas.receipt.show', [
                        'quota' => $this->record->id,
                        'type' => 'pending_notice',
                    ]))
                    ->openUrlInNewTab(),
            ])
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->button(),
            Action::make('sendEmail')
                ->label('Enviar')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->visible(fn (): bool => (bool) Association::query()->value('use_email')
                    && filled($this->record->member?->email)
                    && (bool) ($this->record->member?->email_opt_in)
                    && in_array($this->record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida, QuotaStatus::Pagada], true))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Enviar por email a '.($this->record->member?->email ?? ''))
                ->modalDescription(fn (): string => in_array($this->record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true)
                    ? 'Se enviará el aviso de cuota pendiente al socio.'
                    : 'Se enviará la copia del recibo al socio.')
                ->action(function (): void {
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
                    $memberEmail = (string) $this->record->member?->email;
                    $memberName = $this->record->member?->full_name ?? '-';

                    if (in_array($this->record->status, [QuotaStatus::Pendiente, QuotaStatus::Vencida], true)) {
                        $noticeDate = now()->format('d/m/Y');
                        $pdfBytes = $pdfService->renderView('documents.quotas.QuotaPendingNoticeA5', [
                            'quota' => $this->record,
                            'association' => $association,
                            'noticeDate' => $noticeDate,
                        ], PdfEngine::Dompdf);

                        $mailable = new QuotaPendingNoticeMail($this->record, $pdfBytes, $noticeDate);
                    } else {
                        $pdfBytes = $pdfService->renderView('documents.quotas.QuotaReceiptA5', [
                            'quota' => $this->record,
                            'association' => $association,
                            'receiptType' => 'member',
                        ], PdfEngine::Dompdf);

                        $mailable = new QuotaMemberReceiptMail($this->record, $pdfBytes);
                    }

                    $mailer = Mail::mailer('assoc_dynamic');
                    $mailer->to($memberEmail, $memberName)->send($mailable);

                    Notification::make()
                        ->success()
                        ->title('Email enviado correctamente')
                        ->body('Se ha enviado el email a '.$memberEmail)
                        ->send();
                }),
            Action::make('markAsPaid')
                ->label('Pagar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== QuotaStatus::Pagada)
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
                ->action(function (array $data): void {
                    $paymentMethod = $data['payment_method'] instanceof PaymentMethod
                        ? $data['payment_method']->value
                        : (string) $data['payment_method'];

                    $this->record->markAsPaid(
                        $paymentMethod,
                        filled($data['notes'] ?? null) ? (string) $data['notes'] : null,
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Cuota marcada como pagada')
                        ->actions([
                            Action::make('printReceipt')
                                ->label('Imprimir recibo A4')
                                ->url(route('quotas.receipt.show', ['quota' => $this->record->id, 'type' => 'full']))
                                ->openUrlInNewTab()
                                ->button(),
                        ])
                        ->send();
                }),
            EditAction::make()
                ->label('Editar'),
            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status !== QuotaStatus::Cancelada
                    && $this->record->canBeCancelled())
                ->requiresConfirmation()
                ->action(function (): void {
                    $wasPaid = $this->record->status === QuotaStatus::Pagada;

                    $this->record->update([
                        'status' => QuotaStatus::Cancelada,
                    ]);

                    $this->record->refresh();

                    $notification = Notification::make()
                        ->success()
                        ->title('Cuota cancelada');

                    if ($wasPaid) {
                        $notification->actions([
                            Action::make('printRefund')
                                ->label('Imprimir nota de devolución')
                                ->url(route('quotas.receipt.show', ['quota' => $this->record->id, 'type' => 'refund']))
                                ->openUrlInNewTab()
                                ->button(),
                        ]);
                    }

                    $notification->send();
                }),
        ];
    }
}
