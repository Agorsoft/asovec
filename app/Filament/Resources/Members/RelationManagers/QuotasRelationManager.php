<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\QuotaStatus;
use App\Models\Member;
use App\Models\Quota;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptMember')
                        ->label('Recibo de socio')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->visible(fn (Quota $record): bool => $record->status !== QuotaStatus::Cancelada)
                        ->url(fn (Quota $record): string => route('quotas.receipt.show', [
                            'quota' => $record->id,
                            'type' => 'member',
                        ]))
                        ->openUrlInNewTab(),
                ])
                    ->label('Imprimir')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->button(),
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
