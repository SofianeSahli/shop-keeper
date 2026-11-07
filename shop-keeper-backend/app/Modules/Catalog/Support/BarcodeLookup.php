<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;

final readonly class BarcodeLookup
{
    public const UNKNOWN = 'unknown';

    public function __construct(
        public string $barcode,
        public ?GlobalProduct $catalogProduct,
        public ?ShopProduct $shopProduct,
    ) {}

    public function status(): string
    {
        return $this->catalogProduct?->status->value ?? self::UNKNOWN;
    }
}
