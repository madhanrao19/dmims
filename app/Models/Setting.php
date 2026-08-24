<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable, BelongsToCustomer, HasFactory;

    protected $fillable = ['customer_id', 'setting_group', 'setting_key', 'setting_value', 'setting_type'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Security & Access Control Matrix §3.3 (TENANT_WITH_GLOBAL_DEFAULTS):
    // approved global reference settings (customer_id = null) remain visible
    // to every tenant alongside their own overrides.
    protected static function includesGlobalCustomerDefaults(): bool
    {
        return true;
    }
}
