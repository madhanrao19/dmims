<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'module',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'remarks',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "field_name: old → new" per changed field, in plain English —
     * old_values/new_values store one raw diff per event (not one row per
     * field), and both Box's and Document File's Audit Log tabs plus the
     * platform-wide Audit Logs list need the same humanized rendering, so
     * it lives here once instead of three times.
     */
    public function changesSummary(): string
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $fields = array_unique([...array_keys($old), ...array_keys($new)]);

        if ($fields === []) {
            return '—';
        }

        return collect($fields)
            ->map(fn (string $field): string => sprintf(
                '%s: %s → %s',
                Str::headline($field),
                static::formatChangeValue($old[$field] ?? null),
                static::formatChangeValue($new[$field] ?? null),
            ))
            ->implode("\n");
    }

    protected static function formatChangeValue(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value),
            default => (string) $value,
        };
    }
}
