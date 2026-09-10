<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'customer_id',
        'license_no',
        'deployment_mode',
        'license_mode',
        'installation_id',
        'server_fingerprint',
        'valid_from',
        'valid_to',
        'grace_period_days',
        'status',
        'technical_access_mode',
        'signature',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
