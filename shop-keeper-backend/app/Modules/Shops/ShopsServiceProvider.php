<?php

namespace App\Modules\Shops;

use App\Modules\Shops\Controllers\Middleware\RequireShop;
use App\Modules\Shops\Models\Shop;
use App\Modules\Shops\Policies\ShopPolicy;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ShopsServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        $router->aliasMiddleware(RequireShop::ALIAS, RequireShop::class);

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(Shop::class, ShopPolicy::class);
    }
}
