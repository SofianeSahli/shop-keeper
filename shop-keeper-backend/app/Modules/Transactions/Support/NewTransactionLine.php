<?php

namespace App\Modules\Transactions\Support;

final readonly class NewTransactionLine
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
