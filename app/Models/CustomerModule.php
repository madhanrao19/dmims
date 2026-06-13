<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerModule extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['customer_id', 'module_id', 'is_enabled', 'enabled_at', 'disabled_at', 'created_by', 'updated_by'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
