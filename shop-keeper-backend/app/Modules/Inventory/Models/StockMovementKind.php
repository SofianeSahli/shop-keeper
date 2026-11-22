<?php

namespace App\Modules\Inventory\Models;

enum StockMovementKind: string
{
    case Purchase = 'purchase';
    case PurchaseCancellation = 'purchase_cancellation';
    case OpeningStock = 'opening_stock';
    case Count = 'count';
    case Loss = 'loss';

    public function isAdjustment(): bool
    {
        return in_array($this, [self::OpeningStock, self::Count, self::Loss], true);
    }
}
