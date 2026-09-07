<?php

namespace App\Filament\Resources\BoxResource\Pages;

use App\Filament\Resources\BoxResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * "Overview" tab of the Box detail page. No infolist() override on
 * BoxResource, so ViewRecord falls back to embedding BoxResource::form()
 * read-only — same default Filament uses for every other view page in this
 * app that doesn't define a dedicated infolist.
 */
class ViewBox extends ViewRecord
{
    protected static string $resource = BoxResource::class;

    protected static ?string $navigationLabel = 'Overview';
}
