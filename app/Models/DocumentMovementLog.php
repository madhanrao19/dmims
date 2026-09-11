<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMovementLog extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'movement_no',
        'movable_type',
        'movable_id',
        'action_type',
        'from_location_id',
        'to_location_id',
        'from_box_id',
        'to_box_id',
        'source_origin',
        'destination',
        'scanned_barcode',
        'remarks',
        'metadata',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function fromBox(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'from_box_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function toBox(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'to_box_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * A Box only ever moves between Locations, but a Document File can also
     * move Box-to-Box — showing both raw "From Location"/"From Box" columns
     * (one of them always "—") is the database-jargon this Audit/Movement
     * Log rewrite was meant to remove, so the Box/Document Movement Log tabs
     * show one combined "From"/"To" value instead. A Box's own relation
     * manager never sets from_box_id/to_box_id, so preferring the box name
     * when present is safe for both.
     */
    public function fromLabel(): string
    {
        return $this->fromBox->box_number ?? $this->fromLocation->location_name ?? '—';
    }

    public function toLabel(): string
    {
        return $this->toBox->box_number ?? $this->toLocation->location_name ?? $this->destination ?? '—';
    }
}
