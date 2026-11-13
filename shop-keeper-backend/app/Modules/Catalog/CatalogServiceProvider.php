<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Console\LoadCatalogCommand;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Policies\ShopProductPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public const LOOKUP_LIMITER = 'catalog-lookup';

    private const LOOKUPS_PER_MINUTE = 120;

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        Gate::policy(ShopProduct::class, ShopProductPolicy::class);

        RateLimiter::for(self::LOOKUP_LIMITER, fn (Request $request): Limit => Limit::perMinute(self::LOOKUPS_PER_MINUTE)
            ->by('account:'.$request->user()?->id));

        if ($this->app->runningInConsole()) {
            $this->commands([LoadCatalogCommand::class]);
        }
    }
}
