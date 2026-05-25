<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:update-statuses')]
#[Description('Actualiza automáticamente el estado de los eventos según sus fechas')]
class UpdateEventStatuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now()->startOfDay();

        $finalized = Event::query()
            ->whereIn('status', [EventStatus::Abierto->value, EventStatus::Cerrado->value])
            ->whereDate('end_date', '<', $today)
            ->update(['status' => EventStatus::Finalizado->value]);

        $closed = Event::query()
            ->where('status', EventStatus::Abierto->value)
            ->whereNotNull('registration_deadline')
            ->whereDate('registration_deadline', '<', $today)
            ->whereDate('end_date', '>=', $today)
            ->update(['status' => EventStatus::Cerrado->value]);

        $this->info("Eventos finalizados: {$finalized}. Eventos cerrados (inscripción): {$closed}.");

        return self::SUCCESS;
    }
}
