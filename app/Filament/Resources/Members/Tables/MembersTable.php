<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\QuotaStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                Action::make('exportMembers')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        CheckboxList::make('optional_columns')
                            ->label('Columnas opcionales')
                            ->options([
                                'gender' => 'Género',
                                'birth_date' => 'Fecha Nac.',
                                'payment_method' => 'Método Pago',
                                'status' => 'Estado',
                                'last_paid_date' => 'Fecha Última Cuota',
                                'period_type' => 'Tipo Período',
                                'custom_amount' => 'Importe',
                            ])
                            ->columns(2),
                    ])
                    ->modalHeading('Exportar socios')
                    ->modalSubmitActionLabel('Descargar CSV')
                    ->action(function (array $data, $livewire): StreamedResponse {
                        $selectedOptionalColumns = collect($data['optional_columns'] ?? [])
                            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                            ->values()
                            ->all();

                        $headers = [
                            'Número',
                            'Nombre',
                            'Apellidos',
                            'DNI',
                            'Dirección',
                            'Código Postal',
                            'Localidad',
                            'Provincia',
                            'Teléfono',
                            'Email',
                            'Fecha Alta',
                        ];

                        foreach ($selectedOptionalColumns as $column) {
                            $headers[] = match ($column) {
                                'gender' => 'Género',
                                'birth_date' => 'Fecha Nac.',
                                'payment_method' => 'Método Pago',
                                'status' => 'Estado',
                                'last_paid_date' => 'Fecha Última Cuota',
                                'period_type' => 'Tipo Período',
                                'custom_amount' => 'Importe',
                                default => $column,
                            };
                        }

                        $query = $livewire->getTableQueryForExport()->with([
                            'quotas' => fn (HasMany $quotas): HasMany => $quotas
                                ->where('status', QuotaStatus::Pagada->value)
                                ->latest('paid_at')
                                ->with('quotaType'),
                        ]);

                        $members = $query->get();

                        return response()->streamDownload(function () use ($headers, $members, $selectedOptionalColumns): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            // UTF-8 BOM for Excel compatibility with accents.
                            fwrite($stream, "\xEF\xBB\xBF");

                            fputcsv($stream, $headers, ';');

                            /** @var Member $member */
                            foreach ($members as $member) {
                                $row = [
                                    $member->member_number,
                                    $member->first_name,
                                    $member->last_name,
                                    $member->dni,
                                    $member->address,
                                    $member->postal_code,
                                    $member->city,
                                    $member->province,
                                    $member->phone,
                                    $member->email,
                                    $member->joined_at?->format('d/m/Y'),
                                ];

                                $lastPaidQuota = static::resolveLastPaidQuota($member);

                                foreach ($selectedOptionalColumns as $column) {
                                    $row[] = match ($column) {
                                        'gender' => $member->gender?->value,
                                        'birth_date' => $member->birth_date?->format('d/m/Y'),
                                        'payment_method' => $member->payment_method_id?->value,
                                        'status' => $member->status?->value,
                                        'last_paid_date' => $lastPaidQuota?->paid_at?->format('d/m/Y'),
                                        'period_type' => $lastPaidQuota?->quotaType?->period_type?->value,
                                        'custom_amount' => $lastPaidQuota?->amount !== null
                                            ? number_format((float) $lastPaidQuota->amount, 2, '.', '')
                                            : null,
                                        default => null,
                                    };
                                }

                                fputcsv($stream, $row, ';');
                            }

                            fclose($stream);
                        }, 'socios_export_'.now()->format('Ymd_His').'.csv', [
                            'Content-Type' => 'text/csv; charset=utf-8',
                        ]);
                    }),
            ])
            ->columns([
                TextColumn::make('member_number')
                    ->label('Nº socio')
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? 'socios') !== 'no-socios')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->state(fn ($record): string => trim("{$record->last_name}, {$record->first_name}"))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderBy('last_name', $direction)
                            ->orderBy('first_name', $direction);
                    })
                    ->searchable(['last_name', 'first_name']),
                TextColumn::make('dni')
                    ->label('DNI/NIF')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (MemberStatus $state): string => $state->color())
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MemberStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn (?Member $record): string => $record
                            ? ($record->isNoSocio()
                                ? MemberResource::getUrl('view-non-member', ['record' => $record])
                                : MemberResource::getUrl('view', ['record' => $record]))
                            : MemberResource::getUrl('index')
                        ),
                    EditAction::make()
                        ->url(fn (?Member $record): string => $record
                            ? ($record->isNoSocio()
                                ? MemberResource::getUrl('edit-non-member', ['record' => $record])
                                : MemberResource::getUrl('edit', ['record' => $record]))
                            : MemberResource::getUrl('index')
                        ),
                    Action::make('pasarASocio')
                        ->label('Pasar a Socio')
                        ->icon('heroicon-o-arrow-up-circle')
                        ->color('success')
                        ->visible(fn (?Member $record): bool => $record?->isNoSocio() ?? false)
                        ->requiresConfirmation()
                        ->modalHeading(fn (?Member $record): string => $record
                            ? "Convertir a {$record->first_name} {$record->last_name} en Socio"
                            : 'Convertir a Socio'
                        )
                        ->modalDescription('Se asignará el siguiente número de socio correlativo. Podrá completar los datos restantes en el formulario de edición.')
                        ->action(function (?Member $record): void {
                            if (! $record) {
                                return;
                            }

                            $nextNumber = Association::nextMemberNumber();

                            $record->update([
                                'member_type' => MemberType::Socio->value,
                                'member_number' => (string) $nextNumber,
                                'status' => MemberStatus::Pendiente->value,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Convertido a Socio')
                                ->body("Número de socio asignado: {$nextNumber}. Use el botón Editar para completar los datos.")
                                ->send();
                        })
                        ->successRedirectUrl(fn (?Member $record): string => $record
                            ? MemberResource::getUrl('edit', ['record' => $record->id])
                            : MemberResource::getUrl('index')),
                ]),
            ])
            ->toolbarActions([])
            ->bulkActions([
                BulkAction::make('exportSelected')
                    ->label('Exportar seleccionados')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->schema([
                        CheckboxList::make('optional_columns')
                            ->label('Columnas opcionales')
                            ->options([
                                'gender' => 'Género',
                                'birth_date' => 'Fecha Nac.',
                                'payment_method' => 'Método Pago',
                                'last_paid_date' => 'Fecha Última Cuota',
                                'period_type' => 'Tipo Período',
                                'custom_amount' => 'Importe',
                            ])
                            ->columns(2),
                    ])
                    ->modalHeading('Exportar socios seleccionados')
                    ->modalSubmitActionLabel('Descargar CSV')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (EloquentCollection $records, array $data): StreamedResponse {
                        $selectedOptionalColumns = collect($data['optional_columns'] ?? [])
                            ->filter(fn (mixed $column): bool => is_string($column) && filled($column))
                            ->values()
                            ->all();

                        $records->loadMissing([
                            'quotas' => fn (HasMany $quotas): HasMany => $quotas
                                ->where('status', QuotaStatus::Pagada->value)
                                ->latest('paid_at')
                                ->with('quotaType'),
                        ]);

                        $headers = [
                            'Número',
                            'Nombre',
                            'Apellidos',
                            'DNI',
                            'Dirección',
                            'Código Postal',
                            'Localidad',
                            'Provincia',
                            'Teléfono',
                            'Email',
                            'Fecha Alta',
                        ];

                        foreach ($selectedOptionalColumns as $column) {
                            $headers[] = match ($column) {
                                'gender' => 'Género',
                                'birth_date' => 'Fecha Nac.',
                                'payment_method' => 'Método Pago',
                                'last_paid_date' => 'Fecha Última Cuota',
                                'period_type' => 'Tipo Período',
                                'custom_amount' => 'Importe',
                                default => $column,
                            };
                        }

                        return response()->streamDownload(function () use ($headers, $records, $selectedOptionalColumns): void {
                            $stream = fopen('php://output', 'w');

                            if ($stream === false) {
                                return;
                            }

                            // UTF-8 BOM for Excel compatibility with accents.
                            fwrite($stream, "\xEF\xBB\xBF");

                            fputcsv($stream, $headers, ';');

                            /** @var Member $member */
                            foreach ($records as $member) {
                                $row = [
                                    $member->member_number,
                                    $member->first_name,
                                    $member->last_name,
                                    $member->dni,
                                    $member->address,
                                    $member->postal_code,
                                    $member->city,
                                    $member->province,
                                    $member->phone,
                                    $member->email,
                                    $member->joined_at?->format('d/m/Y'),
                                ];

                                $lastPaidQuota = static::resolveLastPaidQuota($member);

                                foreach ($selectedOptionalColumns as $column) {
                                    $row[] = match ($column) {
                                        'gender' => $member->gender?->value,
                                        'birth_date' => $member->birth_date?->format('d/m/Y'),
                                        'payment_method' => $member->payment_method_id?->value,
                                        'last_paid_date' => $lastPaidQuota?->paid_at?->format('d/m/Y'),
                                        'period_type' => $lastPaidQuota?->quotaType?->period_type?->value,
                                        'custom_amount' => $lastPaidQuota?->amount !== null
                                            ? number_format((float) $lastPaidQuota->amount, 2, '.', '')
                                            : null,
                                        default => null,
                                    };
                                }

                                fputcsv($stream, $row, ';');
                            }

                            fclose($stream);
                        }, 'socios_seleccionados_'.now()->format('Ymd_His').'.csv', [
                            'Content-Type' => 'text/csv; charset=utf-8',
                        ]);
                    }),
            ]);
    }

    private static function resolveLastPaidQuota(Member $member): ?Quota
    {
        $quotas = $member->quotas;

        if (! $quotas instanceof EloquentCollection) {
            return null;
        }

        /** @var ?Quota $quota */
        $quota = $quotas->first(fn (Quota $item): bool => $item->status === QuotaStatus::Pagada);

        return $quota;
    }
}
