<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\EnsureAdminIsActive;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('hoxtan')
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
                    .'<link rel="stylesheet" href="'.asset('css/admin-theme.css').'?v=33">',
            )
            ->authGuard('admin')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->navigationGroups(array_values(array_filter([
                NavigationGroup::make('Dashboard')->collapsed(false),
                NavigationGroup::make('User Management'),
                NavigationGroup::make('Gold Rate Management'),
                NavigationGroup::make('Silver Rate Management'),
                NavigationGroup::make('Investment Management'),
                config('admin_navigation.redemption_management')
                    ? NavigationGroup::make('Redemption Management')
                    : null,
                NavigationGroup::make('Wallet Management'),
                NavigationGroup::make('CMS Management'),
                NavigationGroup::make('Jewellery Management'),
                NavigationGroup::make('Delivery Management'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Notification Management'),
                NavigationGroup::make('System'),
            ])))
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
                EnsureAdminIsActive::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
