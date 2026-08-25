<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Platform Customer 360 (docs/CONFORMANCE_GAP_ANALYSIS.md, 25 Aug 2026
 * design review): the "Overview" tab of a customer's Customer 360 record
 * page. Renders CustomerResource::infolist() — ViewRecord::infolist()
 * already delegates to the owning resource's infolist() automatically.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Customer Overview';

    public static function canAccess(array $parameters = []): bool
    {
        return CustomerResource::canAccessCustomer360($parameters['record'] ?? null);
    }
}
