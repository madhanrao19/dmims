<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\Favoritable;
use App\Models\Concerns\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use Auditable, BelongsToCustomer, Favoritable, HasFactory, SoftDeletes, Taggable;

    protected $fillable = [
        'customer_id',
        'box_barcode',
        'box_number',
        'current_location_id',
        'source_origin',
        'capacity_limit',
        'current_file_count',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function currentLocation()
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function files()
    {
        return $this->hasMany(DocumentFile::class, 'current_box_id');
    }

    public function getCapacityPercentAttribute(): ?int
    {
        if (! $this->capacity_limit) {
            return null;
        }

        return (int) round(min($this->current_file_count, $this->capacity_limit) / $this->capacity_limit * 100);
    }

    /**
     * Full physical chain, e.g. "Warehouse A > Rack B > Shelf S02 > Box BX-008".
     */
    public function getPhysicalPathAttribute(): string
    {
        if (! $this->currentLocation) {
            return $this->status === 'moved_out' ? "Box {$this->box_number} (dispatched)" : "Box {$this->box_number}";
        }

        return "{$this->currentLocation->ancestry_path} > Box {$this->box_number}";
    }
}
