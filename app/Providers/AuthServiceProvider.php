<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\BarcodeRegistry;
use App\Models\Box;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\DocumentMovementLog;
use App\Models\DocumentType;
use App\Models\Export;
use App\Models\Import;
use App\Models\License;
use App\Models\LicenseLog;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\Setting;
use App\Models\StockAdjustmentApproval;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\SubscriptionPlan;
use App\Models\SupportAccessLog;
use App\Models\User;
use App\Policies\ResourcePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Customer::class => ResourcePolicy::class,
        User::class => ResourcePolicy::class,
        Product::class => ResourcePolicy::class,
        Category::class => ResourcePolicy::class,
        Location::class => ResourcePolicy::class,
        LocationType::class => ResourcePolicy::class,
        StockMovement::class => ResourcePolicy::class,
        BarcodeRegistry::class => ResourcePolicy::class,
        Box::class => ResourcePolicy::class,
        ProductLocationStock::class => ResourcePolicy::class,
        DocumentFile::class => ResourcePolicy::class,
        DocumentType::class => ResourcePolicy::class,
        DocumentMovementLog::class => ResourcePolicy::class,
        CustomerSubscription::class => ResourcePolicy::class,
        SubscriptionPlan::class => ResourcePolicy::class,
        CustomerModule::class => ResourcePolicy::class,
        License::class => ResourcePolicy::class,
        LicenseLog::class => ResourcePolicy::class,
        Import::class => ResourcePolicy::class,
        Export::class => ResourcePolicy::class,
        Notification::class => ResourcePolicy::class,
        StockAdjustmentApproval::class => ResourcePolicy::class,
        StockAlert::class => ResourcePolicy::class,
        SupportAccessLog::class => ResourcePolicy::class,
        Setting::class => ResourcePolicy::class,
        AuditLog::class => ResourcePolicy::class,
        Backup::class => ResourcePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Allow platform users to bypass policies globally
        Gate::before(function ($user, $ability) {
            if (isset($user->is_platform_user) && $user->is_platform_user) {
                return true;
            }
        });
    }
}
