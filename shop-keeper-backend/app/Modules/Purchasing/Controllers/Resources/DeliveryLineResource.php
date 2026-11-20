<?php

namespace App\Modules\Purchasing\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'product_id' => $this->shop_product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
        ];
    }
}
