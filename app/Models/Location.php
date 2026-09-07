<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // ancestryPathMap()'s cache must not outlive the data it describes —
        // invalidate on any write so a renamed/reparented/deleted location
        // is never served a stale path from an earlier snapshot.
        static::saved(fn () => static::$ancestryPathCache = []);
        static::deleted(fn () => static::$ancestryPathCache = []);
    }

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

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
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

    /**
     * @return BelongsTo<LocationType, $this>
     */
    public function locationType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class);
    }

    public function boxes()
    {
        return $this->hasMany(Box::class, 'current_location_id');
    }

    public function productLocationStock()
    {
        return $this->hasMany(ProductLocationStock::class, 'location_id');
    }

    /**
     * Location uses SoftDeletes, so the boxes/product_location_stock FK
     * RESTRICT constraints never fire on delete (the row is never actually
     * removed) — without this check, a location holding active boxes or
     * stock could be "deleted" while still being physically in use.
     */
    public function hasLinkedInventory(): bool
    {
        return $this->boxes()->exists()
            || $this->children()->exists()
            || $this->productLocationStock()->exists();
    }

    public function delete(): ?bool
    {
        if ($this->hasLinkedInventory()) {
            return false;
        }

        return parent::delete();
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
     * Backed by ancestryPathMap()'s single-query cache rather than walking
     * ->parent per record — that lazy-loaded walk turns into an N+1 (extra
     * query per level per row) the moment this accessor is used inside a
     * dropdown option list (BoxResource::locationOptions(), LocationResource's
     * parent_id Select), rather than for a single record.
     */
    public function getAncestryPathAttribute(): string
    {
        return static::ancestryPathMap()[$this->id] ?? $this->location_name;
    }

    /**
     * @var array<string, array<int, string>>
     */
    protected static array $ancestryPathCache = [];

    /**
     * All of this tenant's locations' ancestry paths, computed with one
     * query regardless of row count or hierarchy depth. Cached for the
     * request's lifetime, keyed by the acting user's scope — a bare
     * un-keyed cache would leak one user's unscoped/other-tenant snapshot
     * to the next lookup under a long-lived worker (queue, Octane), where
     * multiple users' requests share one PHP process.
     *
     * @return array<int, string> location id => "Room 1 > Area A > Shelf-A01"
     */
    public static function ancestryPathMap(): array
    {
        $user = auth()->user();
        $scopeKey = match (true) {
            $user === null => 'guest',
            $user->is_platform_user => 'platform',
            default => 'customer:'.$user->customer_id,
        };

        if (isset(static::$ancestryPathCache[$scopeKey])) {
            return static::$ancestryPathCache[$scopeKey];
        }

        $locations = static::query()->get(['id', 'parent_id', 'location_name'])->keyBy('id');

        return static::$ancestryPathCache[$scopeKey] = $locations->map(function (self $location) use ($locations): string {
            $names = [];
            $node = $location;
            $depth = 0;

            while ($node && $depth < 10) {
                array_unshift($names, $node->location_name);
                $node = $node->parent_id ? $locations->get($node->parent_id) : null;
                $depth++;
            }

            return implode(' > ', $names);
        })->all();
    }
}
