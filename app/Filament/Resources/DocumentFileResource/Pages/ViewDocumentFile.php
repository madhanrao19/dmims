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
     * Keeps Transfer/Move Out/Return/Timeline reachable from the file's own
     * detail page, not only from the Document Files list row.
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
