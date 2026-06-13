<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductLocationStock extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'location_id',
        'quantity_on_hand',
        'reserved_quantity',
        'available_quantity',
        'last_movement_at',
    ];

    protected $casts = [
        'last_movement_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
