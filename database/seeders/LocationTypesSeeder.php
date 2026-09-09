<?php

namespace Database\Seeders;

use App\Models\LocationType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Default location type taxonomy (docs/DMIMS Data Migration Strategy &
 * Execution Guide.md §13: Warehouse > Building > Floor > Room > Rack >
 * Shelf > Cabinet). LocationType has no customer_id — it's global reference
 * data, like the module catalogue, not per-tenant. Without this, the
 * Location Chain Builder / Batch Generate "Type" dropdowns are empty.
 *
 * Idempotent (firstOrCreate by type_code) — safe to run on every environment,
 * including production, alongside RolesAndPermissionsSeeder.
 */
class LocationTypesSeeder extends Seeder
{
    use WithoutModelEvents;

    public const TYPES = [
        ['type_code' => 'warehouse', 'type_name' => 'Warehouse', 'sort_order' => 1],
        ['type_code' => 'building', 'type_name' => 'Building', 'sort_order' => 2],
        ['type_code' => 'floor', 'type_name' => 'Floor', 'sort_order' => 3],
        ['type_code' => 'room', 'type_name' => 'Room', 'sort_order' => 4],
        ['type_code' => 'rack', 'type_name' => 'Rack', 'sort_order' => 5],
        ['type_code' => 'shelf', 'type_name' => 'Shelf', 'sort_order' => 6],
        ['type_code' => 'cabinet', 'type_name' => 'Cabinet', 'sort_order' => 7],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            LocationType::firstOrCreate(
                ['type_code' => $type['type_code']],
                [...$type, 'status' => 'active'],
            );
        }
    }
}
