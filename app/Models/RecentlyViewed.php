<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecentlyViewed extends Model
{
    protected $table = 'recently_viewed';

    public $timestamps = false;

    protected $fillable = ['user_id', 'viewable_type', 'viewable_id', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Record (or bump) a view; keeps only the latest 10 per user. */
    public static function record(User $user, Model $record): void
    {
        static::updateOrCreate(
            ['user_id' => $user->id, 'viewable_type' => $record->getMorphClass(), 'viewable_id' => $record->getKey()],
            ['viewed_at' => now()],
        );

        $keepIds = static::where('user_id', $user->id)->latest('viewed_at')->limit(10)->pluck('id');
        static::where('user_id', $user->id)->whereNotIn('id', $keepIds)->delete();
    }
}
