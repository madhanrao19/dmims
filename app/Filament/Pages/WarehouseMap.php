<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BoxResource;
use App\Models\Location;
use App\Services\AccessControlService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Clickable Warehouse > Room > Rack > Shelf > Box hierarchy browser
 * (production-readiness roadmap #15), built on the existing Location
 * parent/child tree — no new data model needed.
 */
class WarehouseMap extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Locations';

    protected static ?string $title = 'Warehouse Map';

    protected string $view = 'filament.pages.warehouse-map';

    public ?int $locationId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->is_platform_user) {
            return true;
        }

        return app(AccessControlService::class)->moduleEnabled($user->customer_id, 'stock_inventory');
    }

    public function selectLocation(?int $id): void
    {
        $this->locationId = $id;
    }

    public function getCurrentLocationProperty(): ?Location
    {
        return $this->locationId ? Location::with('parent')->find($this->locationId) : null;
    }

    /**
     * @return Collection<int, Location>
     */
    public function getBreadcrumbProperty(): Collection
    {
        $trail = collect();
        $node = $this->currentLocation;

        while ($node) {
            $trail->prepend($node);
            $node = $node->parent;
        }

        return $trail;
    }

    public function getChildLocationsProperty(): Collection
    {
        return Location::where('parent_id', $this->locationId)->orderBy('location_name')->get();
    }

    public function getBoxesHereProperty(): Collection
    {
        if (! $this->locationId) {
            return collect();
        }

        return $this->currentLocation->boxes()->orderBy('box_number')->get();
    }

    public function boxUrl(int $boxId): string
    {
        return BoxResource::getUrl('edit', ['record' => $boxId]);
    }
}
