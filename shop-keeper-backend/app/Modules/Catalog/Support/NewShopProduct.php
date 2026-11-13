<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\CatalogLanguage;

final readonly class NewShopProduct
{
    public function __construct(
        public string $id,
        public int $shopId,
        public ?Barcode $barcode,
        public ?int $catalogProductId,
        public ?string $name,
        public ?CatalogLanguage $language,
        public int $salePrice,
        public ?int $purchasePrice,
        public bool $confirmedDifferent,
    ) {}
}
