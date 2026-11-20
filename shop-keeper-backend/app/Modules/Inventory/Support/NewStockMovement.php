<?php

namespace App\Modules\Inventory\Support;

final readonly class NewStockMovement
{
    public function __construct(
        public string $productId,
        public int $quantity,
        public string $sourceId,
    ) {}
}
