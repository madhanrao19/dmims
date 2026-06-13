<?php

namespace App\Services;

use App\Models\Location;

class LocationService
{
    public function buildLocationTree(int $customerId): array
    {
        $locations = Location::with('children')
            ->where('customer_id', $customerId)
            ->whereNull('parent_id')
            ->get();

        return $locations->map(fn (Location $location) => $this->serializeLocation($location))->all();
    }

    protected function serializeLocation(Location $location): array
    {
        return [
            'id' => $location->id,
            'name' => $location->location_name,
            'code' => $location->location_code,
            'children' => $location->children->map(fn (Location $child) => $this->serializeLocation($child))->all(),
        ];
    }
}
