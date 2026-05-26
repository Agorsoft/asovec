<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Editar usuario';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action): void {
                    if ($this->record->is_admin && User::where('is_admin', true)->count() <= 1) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar el único administrador del sistema.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
