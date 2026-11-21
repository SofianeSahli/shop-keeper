<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ShopProduct;

final class ChangeStockQuantities
{
    /**
     * @param  array<string, int>  $quantityByProductId
     */
    public function handle(int $shopId, array $quantityByProductId): void
    {
        ksort($quantityByProductId);

        foreach ($quantityByProductId as $productId => $quantity) {
            ShopProduct::query()
                ->ofShop($shopId)
                ->whereUuid($productId)
                ->increment('stock_quantity', $quantity);
        }
    }
}
