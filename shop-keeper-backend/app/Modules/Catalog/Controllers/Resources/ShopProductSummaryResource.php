<?php

namespace App\Modules\Catalog\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ShopProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'name' => $this->name,
            'sale_price' => $this->sale_price,
        ];
    }
}
