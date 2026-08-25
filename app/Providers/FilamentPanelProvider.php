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
use Filament\View\PanelsRenderHook;
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
                // Business-rule access gates (user/company active, subscription,
                // license). Must run after StartSession/AuthenticateSession above
                // so auth()->user() is populated — see bootstrap/app.php.
                'business-access',
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
            // No ->font() override: Filament's default (no custom family) uses the
            // bundled, self-hosted "Inter Variable" font via LocalFontProvider. Calling
            // ->font('Inter') switches to BunnyFontProvider (external CDN), which the
            // same-origin CSP in SecurityHeaders blocks, breaking font loading.
            ->darkMode(true)
            // Filament's default toast position (fixed, top-4/right-4 = 16px
            // from the viewport edge) doesn't account for the panel's own
            // sticky 64px topbar, so a toast overlaps/obscures the search box
            // and user menu on any page that fires one while scrolled to the
            // top (most visibly Scan Center's "Unknown barcode" notification
            // and Reports' validation errors). Push top-aligned toasts below
            // the topbar instead of forking Filament's notification view.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>.fi-no.fi-vertical-align-start{top:5rem}</style>',
            )
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\\Filament\\Pages')
            ->discoverClusters(app_path('Filament/Clusters'), 'App\\Filament\\Clusters');
    }
}
