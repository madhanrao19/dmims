<?php

namespace App\Filament\Resources\DocumentFileResource\RelationManagers;

use App\Filament\Resources\DocumentMovementLogResource;
use App\Models\DocumentFile;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Document File View tab: box assignment/transfer/dispatch history, reusing
 * DocumentMovementLogResource's column definitions scoped to this file via
 * DocumentFile::movementLogs(). Mirrors BoxResource's own
 * MovementLogRelationManager exactly.
 */
class MovementLogRelationManager extends RelationManager
{
    protected static string $relationship = 'movementLogs';

    protected static ?string $title = 'Document Movement Log';

    public function table(Table $table): Table
    {
        /** @var DocumentFile $file */
        $file = $this->getOwnerRecord();

        $table = DocumentMovementLogResource::table($table)
            ->query($file->movementLogs()->getQuery());

        if (! $table->hasCustomRecordUrl()) {
            $table->recordUrl(function (Model $record): ?string {
                foreach (['view', 'edit'] as $action) {
                    if (! DocumentMovementLogResource::hasPage($action) || ! DocumentMovementLogResource::{'can'.ucfirst($action)}($record)) {
                        continue;
                    }

                    return DocumentMovementLogResource::getUrl($action, ['record' => $record]);
                }

                return null;
            });
        }

        return $table;
    }
}
