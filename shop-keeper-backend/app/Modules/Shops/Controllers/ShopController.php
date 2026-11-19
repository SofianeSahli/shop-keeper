<?php

namespace App\Modules\Shops\Controllers;

use App\Modules\Shops\Actions\CreateShop;
use App\Modules\Shops\Controllers\Requests\CreateShopRequest;
use App\Modules\Shops\Controllers\Resources\ShopResource;
use App\Modules\Shops\Models\Shop;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\Gate;

final class ShopController
{
    public function store(CreateShopRequest $request, CreateShop $createShop): ShopResource
    {
        $ownerId = $request->user()->id;
        $name = $request->validated('name');
        $businessType = $request->validated('business_type');
        $city = $request->validated('city');
        $address = $request->validated('address');
        $shop = $createShop->handle($ownerId, $name, $businessType, $city, $address);

        return new ShopResource($shop);
    }

    public function show(string $shop): ShopResource
    {
        $foundShop = Shop::query()->find($shop);

        if ($foundShop === null || Gate::denies('view', $foundShop)) {
            throw new ApiException('shop.forbidden', 403);
        }

        return new ShopResource($foundShop);
    }
}
