<?php

namespace App\Providers\Filament;

use App\Filament\Pages\HelpPage;
use App\Http\Middleware\RedirectToAssociationSetup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->profile()
            ->login()
            ->userMenuItems([
                MenuItem::make()
                    ->label('Ayuda')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->url(fn (): string => HelpPage::getUrl()),
            ])
            ->colors([
                'primary' => Color::Sky,
            ])
            ->font('Poppins')
            ->brandLogo(asset('images/logo.svg'))
            ->favicon(asset('images/favicon.svg'))
            ->sidebarWidth('w-1/4')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                RedirectToAssociationSetup::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Gestión de Socios')
                    ->icon(Heroicon::OutlinedUsers)
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('Gestión de Eventos')
                    ->icon(Heroicon::OutlinedCalendar),
                NavigationGroup::make()
                    ->label('Contabilidad')
                    ->icon(Heroicon::OutlinedCurrencyEuro),
                NavigationGroup::make()
                    ->label('Reportes')
                    ->icon(Heroicon::OutlinedChartBar),
                NavigationGroup::make()
                    ->label('Configuración')
                    ->icon(Heroicon::OutlinedCog6Tooth),
            ]);
    }
}
