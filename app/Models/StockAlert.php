<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'location_id',
        'alert_type',
        'threshold_quantity',
        'current_quantity',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
