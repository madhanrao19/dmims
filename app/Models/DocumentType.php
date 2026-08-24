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

    // Security & Access Control Matrix §3.3 (TENANT_WITH_GLOBAL_DEFAULTS):
    // shared/default document types (customer_id = null) remain visible to
    // every tenant alongside their own.
    protected static function includesGlobalCustomerDefaults(): bool
    {
        return true;
    }
}
