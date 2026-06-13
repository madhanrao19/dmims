<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Carbon;

class LicenseService
{
    public function isLicenseValid($license): bool
    {
        if (! $license) {
            return false;
        }

        return in_array($license->status, ['active', 'near_expiry'], true)
            && (! $license->valid_to || $license->valid_to->isFuture() || $license->valid_to->isToday());
    }

    public function getActiveLicense(int $customerId): ?License
    {
        return License::where('customer_id', $customerId)
            ->whereIn('status', ['active', 'near_expiry'])
            ->where(function ($query) {
                $query->whereNull('valid_to')
                    ->orWhereDate('valid_to', '>=', Carbon::now());
            })
            ->orderByDesc('valid_to')
            ->first();
    }
}
