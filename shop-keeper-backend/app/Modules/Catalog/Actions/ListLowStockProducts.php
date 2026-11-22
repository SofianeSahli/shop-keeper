<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ShopProduct;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListLowStockProducts
{
    public function handle(int $shopId, int $pageSize): CursorPaginator
    {
        return ShopProduct::query()
            ->ofShop($shopId)
            ->whereNotNull('low_stock_level')
            ->whereColumn('stock_quantity', '<=', 'low_stock_level')
            ->with('globalProduct.names')
            ->orderBy('stock_quantity')
            ->orderBy('identity_key')
            ->cursorPaginate($pageSize)
            ->withQueryString();
    }
}
