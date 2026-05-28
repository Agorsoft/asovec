<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\PdfEngine;
use App\Enums\QuotaStatus;
use App\Mail\QuotaMemberReceiptMail;
use App\Mail\QuotaPendingNoticeMail;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Services\PdfService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class QuotasRelationManager extends RelationManager
{
    protected static string $relationship = 'quotas';

    protected static ?string $title = 'Cuotas';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if ($ownerRecord instanceof Member) {
            return $ownerRecord->isSocio();
        }

        return parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de Inscripción')
                    ->schema([
                        TextEntry::make('member.id')
                            ->label('Socio')
                            ->state(function (Quota $record): string {
                                if (! $record->member) {
                                    return '-';
                                }

                                return $record->member->member_number_formatted.' - '.$record->member->full_name;
                            })
                            ->columnSpan(2),
                        TextEntry::make('payment_method')
                            ->label('Método de Pago')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('quotaType.name')
                            ->label('Tipo de cuota')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('receipt_number')
                            ->label('Número de Recibo')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('period_start')
                            ->label('Inicio del Período')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('period_end')
                            ->label('Fin del Período')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('paid_at')
                            ->label('Fecha de Pago')
                            ->dateTime('d/m/Y')
                            ->placeholder('-')
                            ->columnSpan(1),
                        TextEntry::make('due_date')
                            ->label('Fecha de vencimiento')
                            ->date('d/m/Y')
                            ->columnSpan(1),
                        TextEntry::make('amount')
                            ->label('Importe')
                            ->numeric()
                            ->suffix(' EUR')
                            ->columnSpan(1),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (QuotaStatus $state): string => $state->color())
                            ->columnSpan(1),
                    ])
                    ->columnSpan(1)
                    ->columns(5),
                TextEntry::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_start')
            ->columns([
                TextColumn::make('period_start')
                    ->label('Período')
                    ->state(fn ($record): string => ($record->period_start?->format('m/Y') ?? '—').' - '.($record->period_end?->format('m/Y') ?? '—'))
                    ->searchable(),
                TextColumn::make('quotaType.name')
                    ->label('Tipo'),
                TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (QuotaStatus $state): string => $state->color()),
                TextColumn::make('paid_at')
                    ->label('Pagada')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('receipt_number')
                    ->label('Recibo')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(QuotaStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('printRefundReceipt')
                        ->label('Nota de devolución')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => $record->status === QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'refund',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printRefundReceiptAssociation')
                        ->label('Nota de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => $record->status === QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'refund_association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printRefundReceiptMember')
                        ->label('Nota de socio')
                        ->icon('heroicon-o-user')
                        ->color('warning')
                        ->visible(fn (Quota $record): bool => $record->status === QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'refund_member',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptFull')
                        ->label('Recibo completo')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptMember')
                        ->label('Recibo de socio')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => ! in_array($record->status, [QuotaStatus::Cancelada, QuotaStatus::Pendiente], true))
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
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
                ])
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->button(),
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
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
