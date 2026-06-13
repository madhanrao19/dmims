<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = [
        'backup_no',
        'backup_type',
        'storage_location',
        'file_path',
        'file_size',
        'status',
        'started_at',
        'completed_at',
        'created_by',
        'remarks',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
