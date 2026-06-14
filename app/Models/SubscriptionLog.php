<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only subscription history (TDD §8). Not Auditable itself; never updated.
 */
class SubscriptionLog extends Model
{
    use BelongsToCustomer, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'customer_subscription_id',
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

    public function subscription()
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }
}
