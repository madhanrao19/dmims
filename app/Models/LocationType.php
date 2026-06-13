<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationType extends Model
{
    use HasFactory;

    protected $fillable = ['type_code', 'type_name', 'description', 'sort_order', 'status'];
}
