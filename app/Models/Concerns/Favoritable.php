<?php

namespace App\Models\Concerns;

use App\Models\Favorite;
use App\Models\User;

trait Favoritable
{
    public function favoritedBy()
    {
        return $this->morphToMany(User::class, 'favoritable', 'favorites');
    }

    public function isFavoritedBy(User $user): bool
    {
        return Favorite::where('user_id', $user->id)
            ->where('favoritable_type', $this->getMorphClass())
            ->where('favoritable_id', $this->getKey())
            ->exists();
    }

    public function toggleFavorite(User $user): bool
    {
        $existing = Favorite::where('user_id', $user->id)
            ->where('favoritable_type', $this->getMorphClass())
            ->where('favoritable_id', $this->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        Favorite::create([
            'user_id' => $user->id,
            'favoritable_type' => $this->getMorphClass(),
            'favoritable_id' => $this->getKey(),
        ]);

        return true;
    }
}
