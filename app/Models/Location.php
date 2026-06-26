<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'parent_id',
        'location_type_id',
        'location_code',
        'location_name',
        'full_path',
        'barcode',
        'can_store_stock',
        'can_store_boxes',
        'box_capacity',
        'status',
        'created_by',
        'updated_by',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function boxes()
    {
        return $this->hasMany(Box::class, 'current_location_id');
    }

    public function getBoxesUsedCountAttribute(): int
    {
        return $this->boxes()->count();
    }

    public function getBoxCapacityPercentAttribute(): ?int
    {
        if (! $this->box_capacity) {
            return null;
        }

        return (int) round(min($this->boxes_used_count, $this->box_capacity) / $this->box_capacity * 100);
    }

    /**
     * Human-readable ancestry chain, e.g. "Warehouse A > Rack B > Shelf S02".
     * Computed live from the parent chain rather than a maintained
     * `full_path` column, since hierarchies here are shallow (3-4 levels).
     */
    public function getAncestryPathAttribute(): string
    {
        $names = [];
        $node = $this;
        $depth = 0;

        while ($node && $depth < 10) {
            array_unshift($names, $node->location_name);
            $node = $node->parent;
            $depth++;
        }

        return implode(' > ', $names);
    }
}
