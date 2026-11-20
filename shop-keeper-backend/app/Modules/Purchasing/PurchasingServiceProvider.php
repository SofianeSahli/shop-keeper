<?php

namespace App\Modules\Purchasing;

use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Policies\DeliveryPolicy;
use App\Modules\Purchasing\Policies\SupplierPaymentPolicy;
use App\Modules\Purchasing\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PurchasingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Delivery::class, DeliveryPolicy::class);
        Gate::policy(SupplierPayment::class, SupplierPaymentPolicy::class);
    }
}
