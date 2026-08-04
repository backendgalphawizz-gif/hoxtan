<?php

namespace App\Providers\Filament;

use App\Filament\Employee\Pages\Auth\Login;
use App\Http\Middleware\EnsureEmployeeIsActive;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class EmployeePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('employee')
            ->path('employee')
            ->login(Login::class)
            ->brandName('hoxtan Staff / Employee')
            ->brandLogo(fn () => view('admin.layouts.logo'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('images/hoxtan-icon.png'))
            ->colors([
                'primary' => Color::hex('#ea580c'),
                'gray' => Color::Zinc,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Inter', provider: null)
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop(false)
            ->sidebarWidth('16.5rem')
            ->maxContentWidth('full')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="preconnect" href="https://fonts.bunny.net">'
                    .'<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">'
                    .'<link rel="stylesheet" href="'.asset('css/admin-theme.css').'?v=40">',
            )
            ->authGuard('employee')
            ->discoverResources(in: app_path('Filament/Employee/Resources'), for: 'App\\Filament\\Employee\\Resources')
            ->discoverPages(in: app_path('Filament/Employee/Pages'), for: 'App\\Filament\\Employee\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Employee/Widgets'), for: 'App\\Filament\\Employee\\Widgets')
            ->widgets([])
            ->navigationGroups([
                NavigationGroup::make('Team')->collapsed(false),
                NavigationGroup::make('Users')->collapsed(false),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureEmployeeIsActive::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
