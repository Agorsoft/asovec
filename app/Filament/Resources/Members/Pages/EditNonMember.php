<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Schemas\NonMemberForm;
use App\Models\Association;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EditNonMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getAllRelationManagers(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return NonMemberForm::configure($schema);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Editar No Socio: '.trim("{$this->record->first_name} {$this->record->last_name}");
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
                ->modalDescription('Se guardarán los cambios actuales y se asignará el siguiente número de socio correlativo. Continuará en el formulario completo de Socio.')
                ->action(fn () => $this->convertToMember()),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? MemberResource::getUrl('index');
    }

    public function convertToMember(): void
    {
        // Save current form data first
        $this->save(shouldRedirect: false);

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
