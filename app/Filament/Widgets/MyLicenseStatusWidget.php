<?php

namespace App\Filament\Widgets;

use App\Models\License;
use App\Services\AccessControlService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Security & Access Control Matrix §8: customer users get only a simplified
 * own-license status summary (status, access mode, validity, expiry
 * warning) — never the standalone License Management resource, which now
 * requires a platform user (see LicenseResource::$platformOnly). Internal
 * technical fields (server fingerprint, installation id, deployment mode)
 * are intentionally not surfaced here.
 */
class MyLicenseStatusWidget extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ! $user->is_platform_user && $user->customer_id);
    }

    protected function getHeading(): ?string
    {
        return 'License Status';
    }

    protected function getStats(): array
    {
        $customerId = auth()->user()?->customer_id;

        $license = License::where('customer_id', $customerId)
            ->latest('valid_to')
            ->first();

        if (! $license) {
            return [
                Stat::make('Status', 'No License')
                    ->description('Contact your administrator')
                    ->color('danger'),
            ];
        }

        $accessMode = app(AccessControlService::class)->getEffectiveAccessMode($customerId);
        $daysToExpiry = (int) floor(now()->diffInDays($license->valid_to, false));
        $expiring = $daysToExpiry >= 0 && $daysToExpiry <= 30;

        return [
            Stat::make('Status', ucfirst($license->status))
                ->color(in_array($license->status, ['active', 'trial'], true) ? 'success' : 'danger'),
            Stat::make('Access Mode', str_replace('_', ' ', ucfirst($accessMode)))
                ->color($accessMode === AccessControlService::MODE_FULL ? 'success' : 'warning'),
            Stat::make('Valid Until', $license->valid_to->toFormattedDateString())
                ->description($expiring ? "Expires in {$daysToExpiry} day(s)" : null)
                ->descriptionIcon($expiring ? 'heroicon-m-exclamation-triangle' : null)
                ->color($expiring ? 'warning' : 'gray'),
        ];
    }
}
