<?php

namespace App\Filament\Pages;

use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PrintConsentPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Imprimir consentimiento';

    protected static ?string $title = 'Imprimir hoja de consentimiento';

    protected static UnitEnum|string|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.print-consent';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Seleccionar persona')
                    ->schema([
                        Select::make('member_id')
                            ->label('Socio / No socio')
                            ->required()
                            ->searchable()
                            ->options(
                                Member::orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => trim(
                                            ($m->member_number ? "[{$m->member_number_formatted}] " : '').
                                            $m->last_name.', '.$m->first_name
                                        ),
                                    ])
                            )
                            ->helperText('Busca por nombre o apellido. Se incluyen tanto socios como no socios.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openConsent')
                ->label('Abrir hoja de consentimiento')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('primary')
                ->action(function (): void {
                    $state = $this->form->getState();

                    if (empty($state['member_id'])) {
                        Notification::make()
                            ->warning()
                            ->title('Selecciona una persona primero')
                            ->send();

                        return;
                    }

                    $url = route('members.consent.print', ['member' => $state['member_id']]);
                    $this->js("window.open('".addslashes($url)."', '_blank')");
                }),
        ];
    }
}
