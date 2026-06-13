<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BarcodeScanLog extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'barcode',
        'barcode_type',
        'reference_table',
        'reference_id',
        'scan_result',
        'action_taken',
        'scanned_by',
        'scanned_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
