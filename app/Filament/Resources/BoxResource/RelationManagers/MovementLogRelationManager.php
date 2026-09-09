<?php

namespace App\Filament\Resources\BoxResource\RelationManagers;

use App\Filament\Resources\DocumentMovementLogResource;
use App\Models\Box;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Box View tab: location transfer and dispatch history, reusing
 * DocumentMovementLogResource's column definitions scoped to this box via
 * Box::movementLogs().
 */
class MovementLogRelationManager extends RelationManager
{
    protected static string $relationship = 'movementLogs';

    protected static ?string $title = 'Physical Movement History';

    public function table(Table $table): Table
    {
        /** @var Box $box */
        $box = $this->getOwnerRecord();

        $table = DocumentMovementLogResource::table($table)
            ->query($box->movementLogs()->getQuery());

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
