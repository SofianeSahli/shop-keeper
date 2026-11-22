<?php

namespace App\Modules\Inventory\Controllers\Resources;

use App\Modules\Inventory\Support\StockMovementDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $details = $this->details ?? new StockMovementDetails;
        $recordedAt = $this->created_at->toIso8601ZuluString();

        return [
            'id' => $this->uuid(),
            'kind' => $this->kind->value,
            'quantity' => $this->quantity,
            'stock_after' => $this->stock_after,
            'date' => $details->date,
            'recorded_by' => $details->recordedBy,
            'recorded_at' => $recordedAt,
            'adjustment_id' => $details->adjustmentId,
            'transaction_id' => $details->transactionId,
            'counted_quantity' => $details->countedQuantity,
            'shown_quantity' => $details->shownQuantity,
            'reason' => $details->reason,
            'note' => $details->note,
        ];
    }
}
