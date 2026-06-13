<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
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

    public function fromBox()
    {
        return $this->belongsTo(Box::class, 'from_box_id');
    }

    public function toBox()
    {
        return $this->belongsTo(Box::class, 'to_box_id');
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
