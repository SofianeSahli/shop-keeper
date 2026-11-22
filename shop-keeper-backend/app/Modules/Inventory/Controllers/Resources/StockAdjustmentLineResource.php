<?php

namespace App\Modules\Inventory\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockAdjustmentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'product_id' => $this->shop_product_id,
            'quantity' => $this->quantity,
            'counted_quantity' => $this->counted_quantity,
            'shown_quantity' => $this->shown_quantity,
            'reason' => $this->loss_reason?->value,
            'note' => $this->note,
        ];
    }
}
