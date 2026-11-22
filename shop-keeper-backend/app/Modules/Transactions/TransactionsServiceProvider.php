<?php

namespace App\Modules\Transactions;

use App\Modules\Inventory\Contracts\PurchaseLineSources;
use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Policies\TransactionPolicy;
use App\Modules\Transactions\Support\DescribePurchaseLines;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class TransactionsServiceProvider extends ServiceProvider
{
    public array $bindings = [
        PurchaseLineSources::class => DescribePurchaseLines::class,
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(Transaction::class, TransactionPolicy::class);
    }
}
