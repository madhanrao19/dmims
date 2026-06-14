<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingPayment extends Model
{
    use Auditable, BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'billing_record_id',
        'payment_no',
        'amount',
        'payment_method',
        'payment_date',
        'reference_no',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function billingRecord()
    {
        return $this->belongsTo(BillingRecord::class);
    }
}
