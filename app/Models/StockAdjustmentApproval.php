<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustmentApproval extends Model
{
    use Auditable, BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'stock_movement_id',
        'approval_status',
        'requested_by',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
