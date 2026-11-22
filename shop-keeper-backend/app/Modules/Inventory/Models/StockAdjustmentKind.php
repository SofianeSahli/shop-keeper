<?php

namespace App\Modules\Inventory\Models;

enum StockAdjustmentKind: string
{
    case OpeningStock = 'opening_stock';
    case Count = 'count';
    case Loss = 'loss';

    public function movementKind(): StockMovementKind
    {
        return match ($this) {
            self::OpeningStock => StockMovementKind::OpeningStock,
            self::Count => StockMovementKind::Count,
            self::Loss => StockMovementKind::Loss,
        };
    }
}
