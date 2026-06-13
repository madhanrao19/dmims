<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSubscription extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'customer_id',
        'subscription_plan_id',
        'subscription_no',
        'valid_from',
        'valid_to',
        'grace_period_days',
        'max_users',
        'max_products',
        'max_document_files',
        'max_boxes',
        'allowed_reports',
        'enabled_modules',
        'support_level',
        'status',
        'renewal_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allowed_reports' => 'array',
        'enabled_modules' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
