<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;

/**
 * App-wide base for every resource's Edit page — same reasoning as
 * ../Pages/ListRecords.php. Without an explicit getHeaderActions()
 * override, no Edit page anywhere had a working Delete button either.
 *
 * DeleteAction::make() authorizes itself against the resource's own
 * can('delete', $record) — including $deletePermission (Company Admin can
 * edit users but not delete; Company Supervisor/Stock User can edit
 * products but not delete) — automatically.
 */
abstract class EditRecord extends BaseEditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
