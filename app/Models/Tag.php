<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToCustomer, HasFactory;

    protected $fillable = ['customer_id', 'name', 'color'];

    public function documentFiles()
    {
        return $this->morphedByMany(DocumentFile::class, 'taggable');
    }

    public function boxes()
    {
        return $this->morphedByMany(Box::class, 'taggable');
    }
}
