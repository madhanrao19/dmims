<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sku',
        'barcode',
        'product_name',
        'description',
        'category_id',
        'default_location_id',
        'reorder_level',
        'unit_cost',
        'unit_price',
        'status',
        'created_by',
        'updated_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function defaultLocation()
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }
}
