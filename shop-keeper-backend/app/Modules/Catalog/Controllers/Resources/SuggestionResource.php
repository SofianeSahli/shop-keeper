<?php

namespace App\Modules\Catalog\Controllers\Resources;

use App\Modules\Catalog\Support\ProductMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ProductMatch $resource
 */
final class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'catalog_product' => $this->resource->catalogProduct === null
                ? null
                : (new CatalogProductResource($this->resource->catalogProduct))->resolve($request),
            'shop_product' => $this->resource->shopProduct === null
                ? null
                : (new ShopProductSummaryResource($this->resource->shopProduct))->resolve($request),
        ];
    }
}
