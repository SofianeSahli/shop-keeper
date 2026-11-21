<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ShopProduct;
use App\Shared\Database\BinaryUuid;

final class FindProductIdsOfShop
{
    /**
     * @param  list<string>  $productIds
     * @return list<string>
     */
    public function handle(int $shopId, array $productIds): array
    {
        $productBytes = array_map(fn (string $productId): string => BinaryUuid::toBytes($productId), $productIds);

        return ShopProduct::query()
            ->ofShop($shopId)
            ->whereIn('id', $productBytes)
            ->pluck('id')
            ->all();
    }
}
