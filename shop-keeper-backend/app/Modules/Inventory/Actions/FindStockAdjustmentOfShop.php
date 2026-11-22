<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockAdjustment;

final class FindStockAdjustmentOfShop
{
    public function handle(int $shopId, string $adjustmentId): StockAdjustment
    {
        $adjustment = StockAdjustment::query()->ofShop($shopId)->whereUuid($adjustmentId)->firstOrFail();
        $lines = $adjustment->orderedLines();

        return $adjustment->setRelation('lines', $lines);
    }
}
