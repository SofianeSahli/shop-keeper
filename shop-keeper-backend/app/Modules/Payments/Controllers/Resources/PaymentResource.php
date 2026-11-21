<?php

namespace App\Modules\Payments\Controllers\Resources;

use App\Modules\Contacts\Controllers\Resources\ContactSummary;
use App\Shared\Records\CancellationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $contact = ContactSummary::of($this->contact_id, $this->contact_name);
        $cancellation = CancellationResource::of($this->resource);
        $createdAt = $this->created_at->toIso8601ZuluString();

        return [
            'id' => $this->uuid(),
            'direction' => $this->direction->value,
            'contact' => $contact,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'method' => $this->method->value,
            'payment_date' => $this->payment_date,
            'reference' => $this->reference,
            'recorded_by' => $this->created_by,
            'cancellation' => $cancellation,
            'created_at' => $createdAt,
        ];
    }
}
