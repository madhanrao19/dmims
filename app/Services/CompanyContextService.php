<?php

namespace App\Services;

class CompanyContextService
{
    public function getCurrentCustomerId(): ?int
    {
        return auth()->user()?->customer_id;
    }

    public function setCustomerContext(int $customerId): void
    {
        session(['customer_id' => $customerId]);
    }
}
