<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'max_users',
        'max_products',
        'max_document_files',
        'max_boxes',
        'enabled_modules',
        'allowed_reports',
        'status',
        'technical_access_mode',
        'signature',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled_modules' => 'array',
        'allowed_reports' => 'array',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
