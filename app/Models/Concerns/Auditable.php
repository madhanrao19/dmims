<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create/update/delete events for a model into audit_logs, including
 * the changed attributes (old vs new). This is the authoritative audit trail
 * for the platform — request-level logging cannot see which entity changed
 * because Filament routes every action through a single Livewire endpoint.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->writeAuditLog('created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            // Nothing meaningful changed (e.g. only timestamps were touched).
            if (empty($changes)) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->writeAuditLog('updated', $original, $changes);
        });

        static::deleted(function (Model $model): void {
            $model->writeAuditLog('deleted', $model->auditableAttributes($model->getOriginal()), null);
        });
    }

    /**
     * Attributes that must never be written to the audit log.
     *
     * @var list<string>
     */
    protected array $auditExclude = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'updated_at',
        'created_at',
    ];

    protected function writeAuditLog(string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'customer_id' => $this->resolveAuditCustomerId(),
            'user_id' => auth()->id(),
            'module' => $this->getTable(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Strip sensitive and noise attributes before persisting them.
     */
    protected function auditableAttributes(array $attributes): array
    {
        return collect($attributes)
            ->except($this->auditExclude)
            ->all();
    }

    protected function resolveAuditCustomerId(): ?int
    {
        if (array_key_exists('customer_id', $this->getAttributes())) {
            return $this->getAttribute('customer_id');
        }

        return auth()->user()?->customer_id;
    }
}
