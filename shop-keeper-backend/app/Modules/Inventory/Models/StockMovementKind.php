<?php

namespace App\Modules\Inventory\Models;

enum StockMovementKind: string
{
    case Purchase = 'purchase';
    case PurchaseCancellation = 'purchase_cancellation';
}
