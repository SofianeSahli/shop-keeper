<?php

namespace App\Modules\Payments\Support;

use App\Modules\Payments\Models\PaymentDirection;
use App\Modules\Payments\Models\PaymentMethod;

final readonly class NewPayment
{
    public function __construct(
        public string $id,
        public int $shopId,
        public PaymentDirection $direction,
        public ?string $contactId,
        public ?string $transactionId,
        public int $amount,
        public PaymentMethod $method,
        public string $paymentDate,
        public ?string $reference,
        public int $recordedBy,
    ) {}
}
