<?php

namespace App\Modules\Transactions\Support;

final readonly class TransactionForPayment
{
    public function __construct(
        public string $id,
        public ?string $contactId,
        public int $total,
        public string $paymentDirection,
        public bool $isCancelled,
    ) {}
}
