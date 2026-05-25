<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\RegistrationStatus;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Enums\RegistrationType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';
    protected static ?string $title = 'Inscripciones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de Inscripción')
                    ->schema([
                        TextEntry::make('registration_number')
                            ->label('Nº inscripción')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('member.full_name')
                            ->label('Participante')
                            ->placeholder('—')
                            ->columnSpan(3),
                        TextEntry::make('registration_type')
                            ->label('Tipo de inscripción')
                            ->badge()
                            ->columnSpan(1),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->placeholder('—')
                            ->badge()
                            ->color(fn (?RegistrationStatus $state): string => $state?->color() ?? 'gray')
                            ->columnSpan(1),
                        TextEntry::make('sponsorMember.full_name')
                            ->label('Patrocinador')
                            ->placeholder('—')
                            ->visible(fn (Registration $record): bool => $record->registration_type === RegistrationType::Acompanante)
                            ->columnSpanFull(),
                        TextEntry::make('payment_method')
                            ->label('Método de pago')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('paid_at')
                            ->label('Fecha de pago')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->columnSpan(1),
                        TextEntry::make('amount')
                            ->label('Importe base')
                            ->money('EUR')
                            ->columnSpan(1),
                        TextEntry::make('surcharge_amount')
                            ->label('Recargo')
                            ->money('EUR')
                            ->columnSpan(1),
                        TextEntry::make('total_amount')
                            ->label('Importe total')
                            ->state(fn (Registration $record): string => $record->total_amount)
                            ->money('EUR')
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
            ->recordTitleAttribute('registration_number')
            ->columns([
                TextColumn::make('event.name')
                    ->label('Evento')
                    ->searchable(),
                TextColumn::make('registration_type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (RegistrationStatus $state): string => $state->color()),
                TextColumn::make('amount')
                    ->label('Base')
                    ->money('EUR'),
                TextColumn::make('surcharge_amount')
                    ->label('Recargo')
                    ->money('EUR'),
                TextColumn::make('paid_at')
                    ->label('Pagada')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RegistrationStatus::class),
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
                        ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'refund',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printRefundReceiptAssociation')
                        ->label('Nota de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('warning')
                        ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'refund_association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printRefundReceiptParticipant')
                        ->label('Nota de participante')
                        ->icon('heroicon-o-user')
                        ->color('warning')
                        ->visible(fn (Registration $record): bool => $record->status === RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'refund_member',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptFull')
                        ->label('Recibo completo')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'full',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptAssociation')
                        ->label('Recibo de asociación')
                        ->icon('heroicon-o-building-office-2')
                        ->color('gray')
                        ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
                            'type' => 'association',
                        ]))
                        ->openUrlInNewTab(),
                    Action::make('printReceiptParticipant')
                        ->label('Recibo de participante')
                        ->icon('heroicon-o-user')
                        ->color('gray')
                        ->visible(fn (Registration $record): bool => $record->status !== RegistrationStatus::Cancelada)
                        ->url(fn (Registration $record): string => route('registrations.receipt.show', [
                            'registration' => $record->id,
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
