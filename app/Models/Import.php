<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = [
        'customer_id',
        'import_no',
        'import_type',
        'file_name',
        'file_path',
        'status',
        'total_rows',
        'success_rows',
        'failed_rows',
        'uploaded_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function rows()
    {
        return $this->hasMany(ImportRow::class);
    }
}
