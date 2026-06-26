<?php

namespace App\Models\Concerns;

use App\Models\Tag;

trait Taggable
{
    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}
