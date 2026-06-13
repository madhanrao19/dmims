<?php

namespace App\Services;

use Illuminate\Support\Str;

class UserSecurityService
{
    public function isActiveUser($user): bool
    {
        return $user && $user->status === 'active';
    }

    public function enforcePasswordPolicy(string $password): bool
    {
        return Str::of($password)->length() >= 12
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}
