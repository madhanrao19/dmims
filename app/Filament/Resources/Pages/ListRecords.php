<?php

namespace App\Filament\Resources\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords as BaseListRecords;

/**
 * App-wide base for every resource's List page. Filament never auto-adds a
 * Create button — every ListRecords subclass is expected to declare
 * getHeaderActions() itself (confirmed against
 * vendor/filament/filament/src/Commands/FileGenerators/Resources/Pages/ResourceListRecordsPageClassGenerator.php,
 * the actual code the `make:filament-resource` command generates). None of
 * this app's resources ever did, so no List page anywhere had a working
 * Create button — found when a Super Admin couldn't create a Customer
 * through the UI despite full permissions.
 *
 * CreateAction::make() authorizes itself against the resource's own
 * can('create') (and therefore BaseResource::can(), $usageLimitKey, license
 * mode, etc.) automatically — no extra wiring needed here.
 */
abstract class ListRecords extends BaseListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
