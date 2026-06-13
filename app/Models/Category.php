<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use Auditable, BelongsToCustomer, HasFactory, SoftDeletes;

    protected $fillable = ['customer_id', 'category_code', 'category_name', 'description', 'status', 'created_by', 'updated_by'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
