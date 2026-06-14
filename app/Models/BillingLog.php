<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only billing history (TDD §8). Not Auditable itself (it is the audit
 * record); never updated after creation.
 */
class BillingLog extends Model
{
    use BelongsToCustomer, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'billing_record_id',
        'action',
        'old_values',
        'new_values',
        'remarks',
        'performed_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function billingRecord()
    {
        return $this->belongsTo(BillingRecord::class);
    }
}
