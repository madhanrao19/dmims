<?php

namespace App\Services;

use App\Models\CustomerModule;

class ModuleAccessService
{
    public function isModuleEnabled(int $customerId, string $moduleCode): bool
    {
        return CustomerModule::where('customer_id', $customerId)
            ->whereHas('module', fn ($query) => $query->where('module_code', $moduleCode))
            ->where('is_enabled', true)
            ->exists();
    }
}
