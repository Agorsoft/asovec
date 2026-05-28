<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\URL;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected static ?string $title = 'Editar transacción';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (! static::getResource()::canEdit($this->getRecord())) {
            Notification::make()
                ->warning()
                ->title('Esta transacción no se puede editar')
                ->body('Solo se pueden editar transacciones con origen Manual.')
                ->send();

            $this->redirect(TransactionResource::getUrl('view', ['record' => $this->getRecord()]));

            return;
        }

        $this->authorizeAccess();
        $this->fillForm();
        $this->previousUrl = URL::previous();
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->previousUrl ?? $this->getResourceUrl();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToListing')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => TransactionResource::getUrl('index'))
                ->color('gray'),
            DeleteAction::make()
                ->label('Eliminar')
                ->visible(fn (): bool => TransactionResource::canDelete($this->getRecord())),
        ];
    }
}
