<?php

namespace App\Modules\Purchasing\Support;

final readonly class NewDeliveryLine
{
    public function __construct(
        public string $id,
        public string $productId,
        public int $quantity,
        public int $unitPrice,
    ) {}

    public function amount(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
