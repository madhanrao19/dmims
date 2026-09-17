<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * "Overview" tab of the Location detail page — content comes from
 * LocationResource::infolist().
 */
class ViewLocation extends ViewRecord
{
    protected static string $resource = LocationResource::class;

    protected static ?string $navigationLabel = 'Overview';
}
