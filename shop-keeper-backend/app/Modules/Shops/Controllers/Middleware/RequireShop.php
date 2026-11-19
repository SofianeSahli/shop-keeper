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
        $ownerId = $request->user()->id;
        $shopId = $this->findShopIdOfOwner->handle($ownerId);

        if ($shopId === null) {
            throw new ApiException('shop.required', 403);
        }

        $currentShop = new CurrentShop($shopId);
        $this->container->instance(CurrentShop::class, $currentShop);

        return $next($request);
    }
}
