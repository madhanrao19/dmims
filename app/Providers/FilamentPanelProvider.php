<?php

namespace App\Providers;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FilamentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->path(config('filament.path', 'admin'))
            ->authGuard(config('filament.auth.guard', 'web'))
            // Panel routes get NO middleware by default in Filament — without
            // this stack there is no session, cookie encryption or CSRF
            // protection on /admin, and login cannot persist.
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->login()
            ->passwordReset()
            ->profile()
            // Real TOTP app-authentication (enroll, challenge, recovery codes),
            // replacing the old `two_factor_enabled` UI-only toggle. Opt-in
            // per user via the profile page; not globally required.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // --- Branding & visual language ---
            ->brandName('DMIMS')
            ->favicon(asset('icons/icon-192.png'))
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Inter')
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\\Filament\\Pages');
    }
}
