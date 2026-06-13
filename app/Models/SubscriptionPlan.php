<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_code',
        'plan_name',
        'description',
        'max_users',
        'max_products',
        'max_document_files',
        'max_boxes',
        'allowed_reports',
        'enabled_modules',
        'support_level',
        'price',
        'billing_cycle',
        'status',
    ];

    protected $casts = [
        'allowed_reports' => 'array',
        'enabled_modules' => 'array',
    ];
}
