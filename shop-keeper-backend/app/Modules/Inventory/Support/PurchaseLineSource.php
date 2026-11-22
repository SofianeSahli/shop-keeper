<?php

namespace App\Modules\Inventory\Support;

final readonly class PurchaseLineSource
{
    public function __construct(
        public string $transactionId,
        public string $transactionDate,
        public int $recordedBy,
        public ?int $cancelledBy,
        public ?string $cancelledOn,
    ) {}
}
