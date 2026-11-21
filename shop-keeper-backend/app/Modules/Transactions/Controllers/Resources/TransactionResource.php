<?php

namespace App\Modules\Transactions\Controllers\Resources;

use App\Modules\Contacts\Controllers\Resources\ContactSummary;
use App\Modules\Transactions\Models\PaymentState;
use App\Shared\Records\CancellationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $amountPaid = $this->amount_paid;
        $paymentState = PaymentState::of($this->total, $amountPaid);
        $contact = ContactSummary::of($this->contact_id, $this->contact_name);
        $cancellation = CancellationResource::of($this->resource);
        $createdAt = $this->created_at->toIso8601ZuluString();

        return [
            'id' => $this->uuid(),
            'kind' => $this->kind->value,
            'contact' => $contact,
            'transaction_date' => $this->transaction_date,
            'reference' => $this->reference,
            'total' => $this->total,
            'amount_paid' => $amountPaid,
            'amount_left' => $this->total - $amountPaid,
            'payment_state' => $paymentState->value,
            'line_count' => $this->line_count,
            'recorded_by' => $this->created_by,
            'cancellation' => $cancellation,
            'created_at' => $createdAt,
            'lines' => $this->whenLoaded('lines', fn () => TransactionLineResource::collection($this->lines)),
            'payments' => $this->whenLoaded('payments'),
        ];
    }
}
