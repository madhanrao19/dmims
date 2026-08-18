<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToCustomer, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'code',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
