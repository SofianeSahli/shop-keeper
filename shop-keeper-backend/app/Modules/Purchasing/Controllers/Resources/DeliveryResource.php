<?php

namespace App\Modules\Purchasing\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'supplier' => SupplierSummary::of($this->supplier_id, $this->supplier_name),
            'delivery_date' => $this->delivery_date,
            'reference' => $this->reference,
            'total' => $this->total,
            'line_count' => $this->line_count,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'lines' => $this->whenLoaded('lines', fn () => DeliveryLineResource::collection($this->lines)),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment === null ? null : new SupplierPaymentResource($this->payment)),
        ];
    }
}
