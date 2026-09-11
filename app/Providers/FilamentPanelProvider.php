<?php

namespace App\Providers;

use App\Filament\Auth\Login;
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
use Illuminate\Support\Facades\Blade;
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
            ->login(Login::class)
            ->passwordReset()
            ->profile()
            // Real TOTP app-authentication (enroll, challenge, recovery codes),
            // replacing the old `two_factor_enabled` UI-only toggle. Opt-in
            // per user via the profile page; not globally required.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // --- Branding & visual language ---
            // Custom theme so utility classes used in our own page views
            // (resources/views/filament/**, app/Filament/**) are compiled
            // into the CSS bundle — Filament's stock CSS only carries the
            // classes its own package views use, so unscanned pages like
            // Scan Center/Reports silently lost layout/spacing utilities.
            ->viteTheme('resources/css/filament/admin/theme.css')
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
                fn (): string => '<style>.fi-no.fi-vertical-align-start{top:5rem}</style>'.
                    // Login/password-reset/other auth pages all render inside
                    // Filament's shared .fi-simple-layout wrapper — targeting
                    // that class (rather than a per-page renderHook) applies
                    // the background to every current and future auth page
                    // without duplicating the rule. Dark overlay gradient
                    // matches the reference screenshot's contrast/legibility
                    // over the photo; the panel's own light/dark card
                    // (.fi-simple-main) is unaffected and stays readable.
                    '<style>.fi-simple-layout{background:linear-gradient(rgba(15,23,42,.6),rgba(15,23,42,.6)),url(\''.asset('images/login-background.jpg').'\') center/cover no-repeat fixed;min-height:100vh}</style>',
            )
            // Barcode label print buttons (barcode-label.blade.php,
            // batch-barcode-labels.blade.php) live inside Filament action
            // modals, whose content is injected into the DOM after the
            // page loads (opened on demand). Browsers never execute a
            // <script> tag that arrives that way, so a per-modal <script>
            // defining a print function was silently dead. Moving the
            // function to a render hook isn't enough on its own either:
            // Filament navigates between pages via Livewire's wire:navigate
            // (an SPA-style body swap over AJAX), which — same as the modal
            // case — injects the new body's HTML without executing any
            // <script> tag inside it, so a BODY_END hook only ran on the
            // very first hard page load of a visit, not after clicking to
            // any other page. The fix that survives both: bind a single
            // delegated click listener on `document` (which wire:navigate
            // never replaces, only the body's *content*) once, guarded so
            // it can't double-bind if this hook's script tag ever does run
            // again, and target buttons by a data attribute instead of an
            // inline onclick.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => <<<'HTML'
                    <script>
                        if (!window.__dmimsPrintLabelBound) {
                            window.__dmimsPrintLabelBound = true;
                            document.addEventListener('click', function (event) {
                                var btn = event.target.closest('[data-dmims-print]');
                                if (!btn) return;
                                var target = btn.closest('[data-print-root]').querySelector('[data-print-target]');
                                var styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style')).map(function (n) { return n.outerHTML; }).join('');
                                // Keeps a single label from being split across a page break when
                                // a multi-page batch print wraps to more than one sheet.
                                var printCss = '<style>.dmims-barcode-item{break-inside:avoid;page-break-inside:avoid}</style>';
                                var iframe = document.createElement('iframe');
                                iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
                                document.body.appendChild(iframe);
                                // The head- and body-closing tags below are built via string
                                // concatenation ("<" + "/" + "head>", etc.), never written whole:
                                // App Http Middleware InjectPwaScript regex-matches those two
                                // closing-tag substrings anywhere in the full HTML response, not
                                // just real tags, to splice in PWA link/meta/script tags. Writing
                                // them whole here got matched instead of the page's own closing
                                // tags, splicing unrelated HTML into the middle of this script and
                                // corrupting it into invalid JavaScript, which silently broke every
                                // print click.
                                iframe.contentDocument.open();
                                iframe.contentDocument.write('<html><head><title>Print</title>' + styles + printCss + '<' + '/head><body style="padding:24px">' + target.outerHTML + '<' + '/body></html>');
                                iframe.contentDocument.close();
                                var printed = false;
                                var doPrint = function () {
                                    if (printed) return;
                                    printed = true;
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                    setTimeout(function () { iframe.remove(); }, 1000);
                                };
                                iframe.onload = doPrint;
                                setTimeout(doPrint, 500);
                            });
                        }
                    </script>
                    HTML,
            )
            // Global "scan from anywhere" listener (App\Livewire\BarcodeScannerListener)
            // — mounted here rather than per-page so it survives wire:navigate
            // page swaps, same reasoning as the print-label script above.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render("@livewire('barcode-scanner-listener')"),
            )
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->discoverResources(app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\\Filament\\Pages')
            ->discoverClusters(app_path('Filament/Clusters'), 'App\\Filament\\Clusters');
    }
}
