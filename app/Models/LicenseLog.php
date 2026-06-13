<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'license_id',
        'action',
        'old_value',
        'new_value',
        'remarks',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'performed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
