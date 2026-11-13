<?php

namespace App\Modules\Catalog\Support;

final readonly class ShopProductChanges
{
    public function __construct(
        public ?string $name,
        public ?int $salePrice,
        public bool $changesPurchasePrice,
        public ?int $purchasePrice,
        public bool $confirmedDifferent,
    ) {}
}
