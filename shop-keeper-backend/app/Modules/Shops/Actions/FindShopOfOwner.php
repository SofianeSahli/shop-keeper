<?php

namespace App\Modules\Shops\Actions;

use App\Modules\Shops\Controllers\Resources\ShopResource;
use App\Modules\Shops\Models\Shop;

final class FindShopOfOwner
{
    public function handle(int $ownerId): ?ShopResource
    {
        $shop = Shop::query()->where('owner_id', $ownerId)->first();

        return $shop === null ? null : new ShopResource($shop);
    }
}
