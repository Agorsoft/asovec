<?php

namespace App\Http\Middleware;

use App\Enums\EventStatus;
use App\Enums\QuotaStatus;
use App\Models\Event;
use App\Models\Quota;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SyncDailyStatuses
{
    public function handle(Request $request, Closure $next): Response
    {
        $cacheKey = 'daily_statuses_synced_'.today()->toDateString();

        if (! Cache::has($cacheKey)) {
            try {
                $this->syncEventStatuses();
                $this->syncQuotaStatuses();
                Cache::put($cacheKey, true, now()->endOfDay());
            } catch (\Throwable) {
                // Si la base de datos no está disponible, no interrumpir la petición
            }
        }

        return $next($request);
    }

    protected function syncEventStatuses(): void
    {
        $today = now()->startOfDay();

        // Eventos cuya fecha de fin ya pasó → Finalizado
        Event::query()
            ->whereIn('status', [EventStatus::Abierto->value, EventStatus::Cerrado->value])
            ->whereDate('end_date', '<', $today)
            ->update(['status' => EventStatus::Finalizado->value]);

        // Eventos con inscripción cerrada pero el evento aún no ha terminado → Cerrado
        Event::query()
            ->where('status', EventStatus::Abierto->value)
            ->whereNotNull('registration_deadline')
            ->whereDate('registration_deadline', '<', $today)
            ->whereDate('end_date', '>=', $today)
            ->update(['status' => EventStatus::Cerrado->value]);
    }

    protected function syncQuotaStatuses(): void
    {
        // Cuotas Pendiente cuya fecha de vencimiento ya pasó → Vencida
        Quota::query()
            ->where('status', QuotaStatus::Pendiente->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->update(['status' => QuotaStatus::Vencida->value]);
    }
}
