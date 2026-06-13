<?php

namespace App\Providers;

use Filament\Panel;
use Filament\PanelProvider;

class FilamentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->path(config('filament.path', 'admin'))
            ->authGuard(config('filament.auth.guard', 'web'))
            ->authMiddleware(['auth'])
            ->login()
            ->passwordReset()
            ->profile()
            ->discoverResources(app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\\Filament\\Pages');
    }
}
