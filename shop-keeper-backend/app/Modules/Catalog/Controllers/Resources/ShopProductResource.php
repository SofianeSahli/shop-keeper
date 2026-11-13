<?php

namespace App\Modules\Catalog\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ShopProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'name' => $this->name,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'catalog_product' => $this->globalProduct === null ? null : new CatalogProductResource($this->globalProduct),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
