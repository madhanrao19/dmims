<?php

namespace App\Filament\Clusters\MyCompany\Pages;

use App\Filament\Clusters\MyCompany;
use App\Filament\Resources\LocationResource;
use App\Models\Location;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * My Company tab: a read-only view of this company's own locations.
 * Tenant users already fully manage locations via the standalone Locations
 * menu (LocationResource's own top-level nav, unaffected by
 * $consolidatedViaCustomer360) — this tab exists only so a role can see
 * them from the "My Company" overview without leaving it, matching the
 * Security & Access Control Matrix §6 "My Company" model (view, not
 * manage). Reuses LocationResource::table()'s own columns (kept in sync
 * automatically) but strips every Add/Edit/Delete/Batch Generate/bulk
 * action — unlike every other My Company tab, which uses
 * HasEmbeddedResourceTable to embed its resource's table verbatim,
 * actions included.
 */
class Locations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = MyCompany::class;

    protected static ?string $navigationLabel = 'Locations';

    protected static ?string $title = 'Locations';

    /**
     * Deliberately NOT `LocationResource::can('viewAny')` — that also
     * requires the `stock_inventory` module enabled for this customer
     * (BaseResource::can()'s moduleEnabledForUser() gate), which hid this
     * tab entirely for any customer without that module on (real-world
     * regression: "Madhan Inc" saw no Locations tab in My Company at all).
     * This tab is a passive read-only view, not the operational feature the
     * module gates, so it shows for every customer whose role holds the
     * underlying permission — module status only affects whether a row's
     * own View Location link is clickable (`recordUrl()` below already
     * checks `LocationResource::can('view', $record)`, which does apply the
     * module gate, per row).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ! $user->is_platform_user && ($user->can('manage inventory') || $user->can('view inventory')));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return LocationResource::table($table)
            ->query(LocationResource::getEloquentQuery())
            ->recordActions([])
            ->headerActions([])
            ->bulkActions([])
            ->recordUrl(fn (Location $record): ?string => LocationResource::hasPage('view') && LocationResource::can('view', $record)
                ? LocationResource::getUrl('view', ['record' => $record])
                : null);
    }
}
