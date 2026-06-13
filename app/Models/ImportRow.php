<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    use HasFactory;

    protected $fillable = ['import_id', 'row_number', 'row_data', 'validation_status', 'error_messages'];

    protected $casts = [
        'row_data' => 'array',
        'error_messages' => 'array',
    ];
}
