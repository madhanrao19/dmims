<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['customer_id', 'setting_group', 'setting_key', 'setting_value', 'setting_type'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
