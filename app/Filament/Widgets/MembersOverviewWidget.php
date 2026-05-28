<?php

namespace App\Filament\Widgets;

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MembersOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $socios = Member::query()->where('member_type', MemberType::Socio->value);

        $activos = (clone $socios)->where('status', MemberStatus::Activo->value)->count();
        $pendientes = (clone $socios)->where('status', MemberStatus::Pendiente->value)->count();
        $bajas = (clone $socios)->where('status', MemberStatus::Baja->value)->count();
        $noSocios = Member::query()->where('member_type', MemberType::NoSocio->value)->count();

        $indexUrl = MemberResource::getUrl('index');

        return [
            Stat::make('Socios activos', $activos)
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->url($indexUrl.'?tableFilters[status][value]=Activo')
                ->extraAttributes(['class' => 'border border-success-200 dark:border-success-800'])
                ->columnSpan(1),
            Stat::make('Socios pendientes', $pendientes)
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url($indexUrl.'?tableFilters[status][value]=Pendiente')
                ->extraAttributes(['class' => 'border border-warning-200 dark:border-warning-800'])
                ->columnSpan(1),
            Stat::make('Bajas', $bajas)
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->url($indexUrl.'?tableFilters[status][value]=Baja')
                ->extraAttributes(['class' => 'border border-danger-200 dark:border-danger-800'])
                ->columnSpan(1),
            Stat::make('No socios', $noSocios)
                ->icon('heroicon-o-user-minus')
                ->color('gray')
                ->url($indexUrl)
                ->extraAttributes(['class' => 'border border-gray-200 dark:border-gray-800'])
                ->columnSpan(1),
        ];
    }
}
