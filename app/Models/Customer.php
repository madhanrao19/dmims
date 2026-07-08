<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * Tenant isolation: a customer user may only ever see their own company.
     * Without this, any relationship dropdown (e.g. the customer_id Select on
     * resource forms) would enumerate every company on the platform. Platform
     * users and unauthenticated contexts (console, queues, seeders) see all.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ownCompany', function ($builder): void {
            $user = auth()->user();

            if ($user && ! $user->is_platform_user && $user->customer_id) {
                $builder->where($builder->getModel()->getTable().'.id', $user->customer_id);
            }
        });
    }

    protected $fillable = [
        'company_name',
        'company_code',
        'registration_no',
        'tin_no',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
        'deployment_type',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function customerModules()
    {
        return $this->hasMany(CustomerModule::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}
