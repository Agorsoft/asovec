<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Schemas\NonMemberInfolist;
use App\Models\Association;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewNonMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    public function infolist(Schema $schema): Schema
    {
        return NonMemberInfolist::configure($schema);
    }

    public function getTitle(): string|Htmlable
    {
        return trim("{$this->record->first_name} {$this->record->last_name}") ?: 'No Socio';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => MemberResource::getUrl('index'))
                ->color('gray'),
            Action::make('pasarASocio')
                ->label('Pasar a Socio')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => "Convertir a {$this->record->first_name} {$this->record->last_name} en Socio")
                ->modalDescription('Se asignará el siguiente número de socio correlativo. Podrá completar los datos restantes en el formulario de edición.')
                ->action(fn () => $this->convertToMember()),
            Action::make('edit')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => MemberResource::getUrl('edit-non-member', ['record' => $this->record])),
        ];
    }

    public function convertToMember(): void
    {
        $nextNumber = Association::nextMemberNumber();

        $this->record->update([
            'member_type' => MemberType::Socio->value,
            'member_number' => (string) $nextNumber,
            'status' => MemberStatus::Pendiente->value,
        ]);

        Notification::make()
            ->success()
            ->title('Convertido a Socio')
            ->body("Número de socio asignado: {$nextNumber}. Complete los datos restantes.")
            ->send();

        $this->redirect(MemberResource::getUrl('edit', ['record' => $this->record->id]));
    }
}
