<?php

namespace App\Filament\Widgets;

use App\Enums\QuotaStatus;
use App\Filament\Resources\Quotas\QuotaResource;
use App\Models\Quota;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuotasOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $pagadas = Quota::query()->where('status', QuotaStatus::Pagada->value);
        $pendientes = Quota::query()->where('status', QuotaStatus::Pendiente->value);
        $vencidas = Quota::query()->where('status', QuotaStatus::Vencida->value);

        $totalPagadas = (clone $pagadas)->count();
        $sumPagadas = (float) (clone $pagadas)->sum('amount');

        $totalPendientes = (clone $pendientes)->count();
        $sumPendientes = (float) (clone $pendientes)->sum('amount');

        $totalVencidas = (clone $vencidas)->count();
        $sumVencidas = (float) (clone $vencidas)->sum('amount');

        $indexUrl = QuotaResource::getUrl('index');

        return [
            Stat::make('Cuotas pagadas', $totalPagadas)
                ->description(number_format($sumPagadas, 2, ',', '.') . ' €')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url($indexUrl . '?tableFilters[status][value]=Pagada')
                ->extraAttributes(['class' => 'bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800'])
                ->columnSpan(1),
            Stat::make('Cuotas pendientes', $totalPendientes)
                ->description(number_format($sumPendientes, 2, ',', '.') . ' €')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url($indexUrl . '?tableFilters[status][value]=Pendiente')
                ->extraAttributes(['class' => 'border border-warning-200 dark:border-warning-800'])
                ->columnSpan(1),
            Stat::make('Cuotas vencidas', $totalVencidas)
                ->description(number_format($sumVencidas, 2, ',', '.') . ' €')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url($indexUrl . '?tableFilters[status][value]=Vencida')
                ->extraAttributes(['class' => 'border border-danger-200 dark:border-danger-800'])
                ->columnSpan(1),
        ];
    }
}
