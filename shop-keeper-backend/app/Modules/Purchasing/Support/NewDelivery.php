<?php

namespace App\Modules\Purchasing\Support;

final readonly class NewDelivery
{
    /**
     * @param  list<NewDeliveryLine>  $lines
     */
    public function __construct(
        public string $id,
        public int $shopId,
        public ?string $supplierId,
        public string $deliveryDate,
        public ?string $reference,
        public array $lines,
        public ?NewSupplierPayment $payment,
    ) {}

    /**
     * @return list<string>
     */
    public function productIds(): array
    {
        return array_map(fn (NewDeliveryLine $line): string => $line->productId, $this->lines);
    }

    public function total(): int
    {
        $amounts = array_map(fn (NewDeliveryLine $line): int => $line->amount(), $this->lines);

        return array_sum($amounts);
    }
}
