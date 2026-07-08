<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Panel authorization is enforced centrally by
     * App\Filament\Resources\BaseResource::getAuthorizationResponse() (the
     * layered permission/module/license/tenant engine), so no per-model
     * policies are registered. With no policy, the Gate default-DENIES model
     * abilities — any future direct $user->can('update', $record) call fails
     * closed instead of hitting a stale allow path.
     *
     * There is deliberately no Gate::before platform bypass: it made every
     * $user->can() check true for ALL platform users, giving the view-only
     * Datamation Management role full write access (Security & Access Control
     * Matrix violation). Datamation Super Admin holds every permission via its
     * role, so it needs no bypass.
     */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
