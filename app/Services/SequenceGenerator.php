<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Concurrency-safe named counters backed by a single row-locked table.
 * Replaces count()+1 style numbering, which collides under concurrent
 * writes (two requests can read the same count before either inserts).
 */
class SequenceGenerator
{
    public static function next(string $key): int
    {
        DB::table('sequence_counters')->insertOrIgnore([
            'key' => $key,
            'value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::transaction(function () use ($key) {
            $value = (int) DB::table('sequence_counters')->where('key', $key)->lockForUpdate()->value('value') + 1;

            DB::table('sequence_counters')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);

            return $value;
        });
    }
}
