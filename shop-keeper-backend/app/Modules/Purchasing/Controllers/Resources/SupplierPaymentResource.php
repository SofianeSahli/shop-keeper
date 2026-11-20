<?php

namespace App\Modules\Purchasing\Controllers\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid(),
            'supplier' => SupplierSummary::of($this->supplier_id, $this->supplier_name),
            'delivery_id' => $this->delivery_id,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'payment_date' => $this->payment_date,
            'reference' => $this->reference,
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
