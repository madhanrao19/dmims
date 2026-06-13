<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

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
        'status',
        'created_by',
        'updated_by',
    ];

    public function parent()
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

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }
}
