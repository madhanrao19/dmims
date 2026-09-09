<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\Taggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Box extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes, Taggable;

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

    /**
     * @return BelongsTo<Location, $this>
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function files()
    {
        return $this->hasMany(DocumentFile::class, 'current_box_id');
    }

    /**
     * movable_type/movable_id are plain string/int columns (not a Laravel
     * morph map), so this is a manually-scoped hasMany rather than
     * morphMany — matches DocumentMovementService::log()'s own
     * 'box'/'document_file' string convention.
     */
    public function movementLogs()
    {
        return $this->hasMany(DocumentMovementLog::class, 'movable_id')->where('movable_type', 'box');
    }

    /**
     * Same reasoning as movementLogs() — auditable_type stores the model's
     * FQCN (see DocumentMovementService's Box audit writes), not a morph map.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')->where('auditable_type', self::class);
    }

    /**
     * Matches box_number OR box_barcode so a handheld scanner's input
     * (which types the barcode, not the box number) resolves to a box.
     *
     * @return Collection<int, string>
     */
    public static function searchByNumberOrBarcode(string $search, int $limit = 50): Collection
    {
        return static::query()
            ->where('box_number', 'like', "%{$search}%")
            ->orWhere('box_barcode', 'like', "%{$search}%")
            ->limit($limit)
            ->pluck('box_number', 'id');
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
