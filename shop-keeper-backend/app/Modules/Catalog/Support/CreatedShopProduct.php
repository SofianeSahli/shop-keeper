<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\ShopProduct;

final readonly class CreatedShopProduct
{
    public function __construct(
        public ShopProduct $product,
        public bool $wasCreated,
    ) {}
}
