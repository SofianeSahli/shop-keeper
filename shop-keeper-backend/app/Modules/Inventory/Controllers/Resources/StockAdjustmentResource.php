<?php

namespace App\Modules\Inventory\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at->toIso8601ZuluString();

        return [
            'id' => $this->uuid(),
            'kind' => $this->kind->value,
            'adjustment_date' => $this->adjustment_date,
            'line_count' => $this->line_count,
            'recorded_by' => $this->created_by,
            'created_at' => $createdAt,
            'lines' => StockAdjustmentLineResource::collection($this->lines),
        ];
    }
}
