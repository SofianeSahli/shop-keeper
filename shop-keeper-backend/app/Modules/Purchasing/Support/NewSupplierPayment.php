<?php

namespace App\Modules\Purchasing\Support;

use App\Modules\Purchasing\Models\PaymentMethod;

final readonly class NewSupplierPayment
{
    public function __construct(
        public string $id,
        public int $shopId,
        public string $supplierId,
        public ?string $deliveryId,
        public int $amount,
        public PaymentMethod $method,
        public string $paymentDate,
        public ?string $reference,
    ) {}
}
