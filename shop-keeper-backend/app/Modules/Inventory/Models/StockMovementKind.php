<?php

namespace App\Modules\Inventory\Models;

enum StockMovementKind: string
{
    case Delivery = 'delivery';
    case DeliveryCancellation = 'delivery_cancellation';
}
