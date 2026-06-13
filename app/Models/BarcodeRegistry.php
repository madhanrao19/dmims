<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarcodeRegistry extends Model
{
    use BelongsToCustomer, HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'barcode',
        'barcode_type',
        'reference_table',
        'reference_id',
        'status',
        'printed_count',
        'last_scanned_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
