<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingRecord extends Model
{
    use Auditable, BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'invoice_no',
        'invoice_date',
        'due_date',
        'amount',
        'tax_amount',
        'total_amount',
        'billing_status',
        'payment_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(BillingPayment::class);
    }

    public function logs()
    {
        return $this->hasMany(BillingLog::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->total_amount - $this->paidAmount(), 2);
    }
}
