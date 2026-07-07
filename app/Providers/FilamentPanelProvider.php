<?php

namespace App\Providers;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
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
            // Base HTTP middleware for the panel. Without this stack the panel
            // routes start no session and set no cookie, so browser login can
            // never persist (auth bounces back to /login). This is the standard
            // Filament panel middleware set — it must be present.
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
            ])
            ->authMiddleware(['auth'])
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
