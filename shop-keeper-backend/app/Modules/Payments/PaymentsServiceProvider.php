<?php

namespace App\Modules\Payments;

use App\Modules\Contacts\Contracts\ContactBalances;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Policies\PaymentPolicy;
use App\Modules\Payments\Support\PaymentFigures;
use App\Modules\Transactions\Contracts\TransactionPayments;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ContactBalances::class, PaymentFigures::class);
        $this->app->bind(TransactionPayments::class, PaymentFigures::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
