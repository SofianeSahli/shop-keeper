<?php

namespace App\Modules\Shops\Controllers\Middleware;

use App\Modules\Shops\Actions\FindShopIdOfOwner;
use App\Shared\CurrentShop;
use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireShop
{
    public const ALIAS = 'shop';

    public function __construct(private FindShopIdOfOwner $findShopIdOfOwner, private Container $container) {}

    public function handle(Request $request, Closure $next): Response
    {
        $shopId = $this->findShopIdOfOwner->handle($request->user()->id);

        if ($shopId === null) {
            throw new ApiException('shop.required', 403);
        }

        $this->container->instance(CurrentShop::class, new CurrentShop($shopId));

        return $next($request);
    }
}
