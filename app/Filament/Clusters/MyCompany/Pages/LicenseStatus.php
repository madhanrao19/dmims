<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Widgets\MyLicenseStatusWidget;
use Filament\Pages\Page;

/**
 * Security & Access Control Matrix §8: a simplified own-license status view
 * (status, access mode, valid to, expiry warning — no internal technical
 * fields). Reuses the exact same widget already shown on the Dashboard,
 * rather than redefining the license query/fields here.
 */
class LicenseStatus extends Page
{
    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'License Status';

    protected static ?string $title = 'License Status';

    protected string $view = 'filament-panels::pages.page';

    public static function canAccess(): bool
    {
        return MyLicenseStatusWidget::canView();
    }

    protected function getFooterWidgets(): array
    {
        return [MyLicenseStatusWidget::class];
    }
}
