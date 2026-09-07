<?php

namespace App\Filament\Resources\DocumentFileResource\Pages;

use App\Filament\Resources\DocumentFileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * "Overview" tab of the Document File detail page — see BoxResource\Pages\ViewBox
 * for why no infolist() override is needed.
 */
class ViewDocumentFile extends ViewRecord
{
    protected static string $resource = DocumentFileResource::class;

    protected static ?string $navigationLabel = 'Overview';

    /**
     * Scanning a file's barcode lands here (ScannerService::recordUrl()) —
     * without these, an operator has to go back to the Document Files list
     * to transfer/dispatch/return the very file they just scanned.
     */
    protected function getHeaderActions(): array
    {
        return [
            DocumentFileResource::transferFileAction(),
            DocumentFileResource::moveOutFileAction(),
            DocumentFileResource::returnFileAction(),
            DocumentFileResource::timelineAction(),
            EditAction::make(),
        ];
    }
}
