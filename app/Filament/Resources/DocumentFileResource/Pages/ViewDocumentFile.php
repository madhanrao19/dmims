<?php

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\DocumentFileResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * "Overview" tab of the Document File detail page — see BoxResource\Pages\ViewBox
 * for why no infolist() override is needed.
 */
class ViewDocumentFile extends ViewRecord
{
    protected static string $resource = DocumentFileResource::class;

    protected static ?string $navigationLabel = 'Overview';
}
