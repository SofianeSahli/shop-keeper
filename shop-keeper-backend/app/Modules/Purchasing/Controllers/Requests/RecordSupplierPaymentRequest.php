<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use App\Modules\Purchasing\Controllers\Requests\Rules\PaymentRules;
use App\Modules\Purchasing\Models\PaymentMethod;
use App\Modules\Purchasing\Support\NewSupplierPayment;
use App\Shared\ShopClock;
use Illuminate\Foundation\Http\FormRequest;

final class RecordSupplierPaymentRequest extends FormRequest
{
    public function rules(ShopClock $shopClock): array
    {
        $today = $shopClock->todayAsDate();

        return [
            'id' => ['required', 'string', 'uuid'],
            'supplier_id' => ['required', 'string', 'uuid'],
            'amount' => PaymentRules::amount('required'),
            'method' => PaymentRules::method('required'),
            'payment_date' => ['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reference' => PaymentRules::reference(),
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'supplier_id.required' => 'supplier_id.required',
            'supplier_id.string' => 'supplier_id.invalid',
            'supplier_id.uuid' => 'supplier_id.invalid',
            'payment_date.date_format' => 'payment_date.invalid',
            'payment_date.before_or_equal' => 'payment_date.in_future',
            ...PaymentRules::messages(''),
        ];
    }

    public function newSupplierPayment(int $shopId, ShopClock $shopClock): NewSupplierPayment
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $supplierId = $this->validated('supplier_id');
        $lowercaseSupplierId = strtolower($supplierId);
        $amount = $this->validated('amount');
        $method = $this->validated('method');
        $paymentMethod = PaymentMethod::from($method);
        $today = $shopClock->todayAsDate();
        $paymentDate = $this->validated('payment_date') ?? $today;
        $reference = $this->validated('reference');

        return new NewSupplierPayment(
            id: $lowercaseId,
            shopId: $shopId,
            supplierId: $lowercaseSupplierId,
            deliveryId: null,
            amount: $amount,
            method: $paymentMethod,
            paymentDate: $paymentDate,
            reference: $reference,
        );
    }
}
