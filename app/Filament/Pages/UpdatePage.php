<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class UpdatePage extends Page
{
    protected static ?string $navigationLabel = 'Actualizar';

    protected static ?string $title = 'Actualización de la aplicación';

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.update-page';

    public static function canAccess(): bool
    {
        return Auth::user()?->is_admin ?? false;
    }
}
