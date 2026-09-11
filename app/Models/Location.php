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

        // children()/boxes() and getAncestryPathAttribute()'s parent walk
        // assume a well-formed, single-tenant tree — nothing before this
        // stopped a parent_id edit from creating a cycle (a location
        // becoming its own ancestor, infinite-looping the ancestry walk) or
        // pointing at another customer's location (BelongsToCustomer's
        // tenant scope only guards customer_id, not parent_id).
        static::saving(function (self $location): ?bool {
            if (! $location->isDirty('parent_id') || ! $location->parent_id) {
                return null;
            }

            $node = static::withoutGlobalScopes()->find($location->parent_id);

            for ($depth = 0; $node && $depth < 50; $depth++) {
                if ($node->customer_id !== $location->customer_id) {
                    return false;
                }

                if ($location->exists && $node->id === $location->id) {
                    return false;
                }

                $node = $node->parent_id ? static::withoutGlobalScopes()->find($node->parent_id) : null;
            }

            return null;
        });
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
     * Matches location_name OR barcode so a handheld scanner's input (which
     * types the shelf/rack barcode, not its name) resolves a location — same
     * reasoning as Box::searchByNumberOrBarcode(). Used by Box Transfer/
     * Return's location picker, which — unlike a ->relationship() Select's
     * ->searchable(['col']) shorthand — needs a manual search callback since
     * it's a plain to_location_id action field, not tied to an Eloquent
     * relationship on the acting model.
     *
     * @return array<int, string> location id => full ancestry path
     */
    public static function searchByNameOrBarcode(string $search, int $limit = 50): array
    {
        // The two conditions MUST be grouped in a nested where() — a bare
        // ->where()->orWhere() chained directly onto the query breaks out of
        // BelongsToCustomer's global scope (also a plain top-level ->where()),
        // turning "customer_id = X AND name LIKE ?" OR "barcode LIKE ?" into
        // a query that returns barcode matches from every tenant, not just
        // this one.
        $ids = static::query()
            ->where(function ($query) use ($search): void {
                $query->where('location_name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->pluck('id');

        $paths = static::ancestryPathMap();

        return $ids->mapWithKeys(fn (int $id): array => [$id => $paths[$id] ?? null])->filter()->all();
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

            // 50 matches booted()'s own cycle-guard walk depth above — high
            // enough that no real chain (the Location Chain Builder has no
            // row cap) hits it, only a genuinely corrupted/cyclic tree that
            // somehow bypassed that guard.
            while ($node && $depth < 50) {
                array_unshift($names, $node->location_name);
                $node = $node->parent_id ? $locations->get($node->parent_id) : null;
                $depth++;
            }

            return implode(' > ', $names);
        })->all();
    }
}
