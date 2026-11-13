<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;

final readonly class ProductMatch
{
    public function __construct(
        public ?GlobalProduct $catalogProduct,
        public ?ShopProduct $shopProduct,
    ) {}

    public static function ofShopProduct(ShopProduct $shopProduct): self
    {
        return new self($shopProduct->globalProduct, $shopProduct);
    }

    public function groupKey(): string
    {
        return $this->catalogProduct === null
            ? 'shop:'.$this->shopProduct->uuid()
            : 'catalog:'.$this->catalogProduct->id;
    }

    public function displayName(): string
    {
        return $this->shopProduct?->name ?? $this->catalogProduct?->nameIn(CatalogLanguage::French) ?? '';
    }
}
