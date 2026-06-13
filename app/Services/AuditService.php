<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Arr;

class AuditService
{
    public function record(array $data): void
    {
        AuditLog::create([
            'customer_id' => Arr::get($data, 'customer_id'),
            'user_id' => Arr::get($data, 'user_id'),
            'module' => Arr::get($data, 'module'),
            'action' => Arr::get($data, 'action'),
            'auditable_type' => Arr::get($data, 'auditable_type'),
            'auditable_id' => Arr::get($data, 'auditable_id'),
            'old_values' => Arr::get($data, 'old_values'),
            'new_values' => Arr::get($data, 'new_values'),
            'ip_address' => Arr::get($data, 'ip_address'),
            'user_agent' => Arr::get($data, 'user_agent'),
            'remarks' => Arr::get($data, 'remarks'),
        ]);
    }
}
