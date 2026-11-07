<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Support\BarcodeLookup;

final class LookUpBarcode
{
    public function handle(int $shopId, Barcode $barcode): BarcodeLookup
    {
        $catalogProduct = GlobalProduct::query()->with('names')->where('barcode', $barcode->value)->first();

        $shopProduct = $catalogProduct === null
            ? null
            : ShopProduct::query()->ofShop($shopId)->where('global_product_id', $catalogProduct->id)->first();

        return new BarcodeLookup($barcode->value, $catalogProduct, $shopProduct);
    }
}
