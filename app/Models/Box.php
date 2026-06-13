<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

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
}
