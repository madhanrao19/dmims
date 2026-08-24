<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\AccessControlService;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable, SoftDeletes;

    /**
     * Panel access (Filament checks this in every non-local environment —
     * without implementing FilamentUser, production would deny all logins).
     * Delegates to the documented layer-1 rule: active user, active company,
     * license not blocked.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return app(AccessControlService::class)->canLogin($this);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'name',
        'email',
        'username',
        'employee_id',
        'phone',
        'department_id',
        'job_title',
        'password',
        'status',
        'is_platform_user',
    ];

    public function hasAppAuthenticationEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Filament's default UiAvatarsProvider calls out to ui-avatars.com; that
     * external request fails in offline/firewalled deployments and renders
     * as a broken image. A locally-generated single-letter SVG needs no
     * network access and matches every environment.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        $initial = mb_strtoupper(mb_substr(trim((string) ($this->name ?: $this->email)), 0, 1)) ?: '?';

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
                <rect width="40" height="40" rx="20" fill="#4f46e5" />
                <text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="18" fill="#ffffff">{$initial}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_user' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
