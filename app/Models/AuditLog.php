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
     * Foreign-key columns that show up in Box/Document File audit trails,
     * mapped to the related model + the attribute a person actually
     * recognises — without this, "Current Location: 27 → 28" is exactly the
     * database-jargon the Audit Log is meant to hide, not a location name a
     * user scanned a box into. Deliberately just the fields these two
     * models' audit trails actually produce, not a generic "every _id
     * column in the app" resolver — see resolveForeignKeyLabel()'s
     * doc-comment for the fallback when a field isn't listed here.
     *
     * @var array<string, array{class-string<Model>, string}>
     */
    protected static array $foreignKeyLabels = [
        'customer_id' => [Customer::class, 'company_name'],
        'user_id' => [User::class, 'name'],
        'current_location_id' => [Location::class, 'location_name'],
        'to_location_id' => [Location::class, 'location_name'],
        'from_location_id' => [Location::class, 'location_name'],
        'current_box_id' => [Box::class, 'box_number'],
        'to_box_id' => [Box::class, 'box_number'],
        'from_box_id' => [Box::class, 'box_number'],
        'document_type_id' => [DocumentType::class, 'type_name'],
        'department_id' => [Department::class, 'name'],
    ];

    /**
     * "Field Name: old → new" per changed field, in plain English —
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
                static::formatFieldLabel($field),
                static::formatChangeValue($field, $old[$field] ?? null),
                static::formatChangeValue($field, $new[$field] ?? null),
            ))
            ->implode("\n");
    }

    /**
     * "current_location_id" -> "Current Location" — the "_id" suffix is a
     * database-column artefact, not something a reader needs, and reads
     * strangely once headlined on its own ("Current Location Id").
     */
    protected static function formatFieldLabel(string $field): string
    {
        return Str::headline(Str::endsWith($field, '_id') ? substr($field, 0, -3) : $field);
    }

    protected static function formatChangeValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (isset(static::$foreignKeyLabels[$field]) && is_numeric($value)) {
            return static::resolveForeignKeyLabel($field, (int) $value);
        }

        return (string) $value;
    }

    /**
     * A deleted/renamed related record still has to show *something* for a
     * historical audit row, so this falls back to "#<id>" (findable by a
     * platform user searching that resource directly) rather than the raw
     * column value alone, which would look identical to — and be mistaken
     * for — an actually-unmapped field.
     */
    protected static function resolveForeignKeyLabel(string $field, int $id): string
    {
        static $cache = [];

        if (array_key_exists("{$field}:{$id}", $cache)) {
            return $cache["{$field}:{$id}"];
        }

        [$class, $attribute] = static::$foreignKeyLabels[$field];

        /** @var Model|null $related */
        $related = $class::withoutGlobalScopes()->find($id);

        return $cache["{$field}:{$id}"] = $related?->getAttribute($attribute) ?? "#{$id}";
    }
}
