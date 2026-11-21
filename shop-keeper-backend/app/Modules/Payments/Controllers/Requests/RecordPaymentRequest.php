<?php

namespace App\Modules\Payments\Controllers\Requests;

use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentDirection;
use App\Modules\Payments\Models\PaymentMethod;
use App\Modules\Payments\Support\NewPayment;
use App\Shared\ShopClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class RecordPaymentRequest extends FormRequest
{
    public function rules(ShopClock $shopClock): array
    {
        $today = $shopClock->todayAsDate();
        $paymentDirection = Rule::enum(PaymentDirection::class);
        $paymentMethod = Rule::enum(PaymentMethod::class);

        return [
            'id' => ['required', 'string', 'uuid'],
            'direction' => ['required', $paymentDirection],
            'contact_id' => ['nullable', 'required_without:transaction_id', 'string', 'uuid'],
            'transaction_id' => ['nullable', 'string', 'uuid'],
            'amount' => ['required', 'integer:strict', 'min:1', 'max:'.Payment::MAX_AMOUNT_MILLIMES],
            'method' => ['required', $paymentMethod],
            'payment_date' => ['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reference' => ['nullable', 'string', 'max:'.Payment::MAX_REFERENCE_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'direction.required' => 'direction.required',
            'direction.'.Enum::class => 'direction.invalid',
            'contact_id.required_without' => 'contact_id.required',
            'contact_id.string' => 'contact_id.invalid',
            'contact_id.uuid' => 'contact_id.invalid',
            'transaction_id.string' => 'transaction_id.invalid',
            'transaction_id.uuid' => 'transaction_id.invalid',
            'amount.required' => 'amount.required',
            'amount.integer' => 'amount.integer',
            'amount.min' => 'amount.too_low',
            'amount.max' => 'amount.too_high',
            'method.required' => 'method.required',
            'method.'.Enum::class => 'method.invalid',
            'payment_date.date_format' => 'payment_date.invalid',
            'payment_date.before_or_equal' => 'payment_date.in_future',
            'reference.string' => 'reference.invalid',
            'reference.max' => 'reference.too_long',
        ];
    }

    public function newPayment(int $shopId, int $accountId, ShopClock $shopClock): NewPayment
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $direction = $this->validated('direction');
        $paymentDirection = PaymentDirection::from($direction);
        $contactId = $this->validated('contact_id');
        $lowercaseContactId = $contactId === null ? null : strtolower($contactId);
        $transactionId = $this->validated('transaction_id');
        $lowercaseTransactionId = $transactionId === null ? null : strtolower($transactionId);
        $amount = $this->validated('amount');
        $method = $this->validated('method');
        $paymentMethod = PaymentMethod::from($method);
        $today = $shopClock->todayAsDate();
        $paymentDate = $this->validated('payment_date') ?? $today;
        $reference = $this->validated('reference');

        return new NewPayment(
            id: $lowercaseId,
            shopId: $shopId,
            direction: $paymentDirection,
            contactId: $lowercaseContactId,
            transactionId: $lowercaseTransactionId,
            amount: $amount,
            method: $paymentMethod,
            paymentDate: $paymentDate,
            reference: $reference,
            recordedBy: $accountId,
        );
    }
}
