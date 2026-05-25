<?php

namespace App\Filament\Widgets;

use App\Enums\EventStatus;
use App\Enums\RegistrationStatus;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Widgets\Widget;

class LastEventWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.last-event-widget';

    protected int | string | array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function getEvent(): ?Event
    {
        return Event::query()
            ->where('status', EventStatus::Abierto->value)
            ->orderByDesc('start_date')
            ->first();
    }

    public function getViewData(): array
    {
        $event = $this->getEvent();

        if (! $event) {
            return ['event' => null, 'confirmadas' => 0, 'pendientes' => 0, 'eventUrl' => null];
        }

        $confirmadas = $event->registrations()
            ->where('status', RegistrationStatus::Confirmada->value)
            ->count();

        $pendientes = $event->registrations()
            ->where('status', RegistrationStatus::Pendiente->value)
            ->count();

        return [
            'event' => $event,
            'confirmadas' => $confirmadas,
            'pendientes' => $pendientes,
            'eventUrl' => EventResource::getUrl('view', ['record' => $event]),
        ];
    }
}
