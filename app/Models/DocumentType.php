<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use Auditable, BelongsToCustomer, HasFactory;

    protected $fillable = ['customer_id', 'type_code', 'type_name', 'description', 'status'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
