<?php

namespace App\Modules\Purchasing\Controllers\Requests;

use App\Modules\Catalog\Controllers\Requests\Rules\PriceInMillimes;
use App\Modules\Purchasing\Controllers\Requests\Rules\PaymentRules;
use App\Modules\Purchasing\Models\PaymentMethod;
use App\Modules\Purchasing\Support\NewDelivery;
use App\Modules\Purchasing\Support\NewDeliveryLine;
use App\Modules\Purchasing\Support\NewSupplierPayment;
use App\Shared\ShopClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RecordDeliveryRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public const MAX_QUANTITY = 999_999;

    public const MAX_REFERENCE_LENGTH = 50;

    public function rules(ShopClock $shopClock): array
    {
        $today = $shopClock->todayAsDate();
        $paymentNeedsSupplier = Rule::prohibitedIf(fn (): bool => ! $this->filled('supplier_id'));

        return [
            'id' => ['required', 'string', 'uuid'],
            'supplier_id' => ['nullable', 'string', 'uuid'],
            'delivery_date' => ['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reference' => ['nullable', 'string', 'max:'.self::MAX_REFERENCE_LENGTH],
            'lines' => ['required', 'array', 'max:'.self::MAX_LINES],
            'lines.*' => ['array'],
            'lines.*.id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            'lines.*.product_id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_QUANTITY],
            'lines.*.unit_price' => PriceInMillimes::rules(required: true),
            'payment' => ['nullable', 'bail', 'array', $paymentNeedsSupplier],
            'payment.id' => ['required_with:payment', 'string', 'uuid'],
            'payment.amount' => PaymentRules::amount('required_with:payment'),
            'payment.method' => PaymentRules::method('required_with:payment'),
            'payment.reference' => PaymentRules::reference(),
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'supplier_id.string' => 'supplier_id.invalid',
            'supplier_id.uuid' => 'supplier_id.invalid',
            'delivery_date.date_format' => 'delivery_date.invalid',
            'delivery_date.before_or_equal' => 'delivery_date.in_future',
            'reference.string' => 'reference.invalid',
            'reference.max' => 'reference.too_long',
            'lines.required' => 'lines.required',
            'lines.array' => 'lines.required',
            'lines.max' => 'lines.too_many',
            'lines.*.array' => 'line.invalid',
            'lines.*.id.required' => 'id.required',
            'lines.*.id.string' => 'id.invalid',
            'lines.*.id.uuid' => 'id.invalid',
            'lines.*.id.distinct' => 'id.duplicate',
            'lines.*.product_id.required' => 'product_id.required',
            'lines.*.product_id.string' => 'product_id.invalid',
            'lines.*.product_id.uuid' => 'product_id.invalid',
            'lines.*.product_id.distinct' => 'product_id.duplicate',
            'lines.*.quantity.required' => 'quantity.required',
            'lines.*.quantity.integer' => 'quantity.integer',
            'lines.*.quantity.min' => 'quantity.too_low',
            'lines.*.quantity.max' => 'quantity.too_high',
            'lines.*.unit_price.required' => 'unit_price.required',
            'lines.*.unit_price.integer' => 'unit_price.integer',
            'lines.*.unit_price.min' => 'unit_price.negative',
            'lines.*.unit_price.max' => 'unit_price.too_high',
            'payment.array' => 'payment.invalid',
            'payment.prohibited' => 'payment.supplier_required',
            'payment.id.required_with' => 'id.required',
            'payment.id.string' => 'id.invalid',
            'payment.id.uuid' => 'id.invalid',
            ...PaymentRules::messages('payment.'),
        ];
    }

    public function newDelivery(int $shopId, ShopClock $shopClock): NewDelivery
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $supplierId = $this->validated('supplier_id');
        $lowercaseSupplierId = $supplierId === null ? null : strtolower($supplierId);
        $today = $shopClock->todayAsDate();
        $deliveryDate = $this->validated('delivery_date') ?? $today;
        $reference = $this->validated('reference');
        $lines = $this->lines();
        $payment = $this->payment($shopId, $lowercaseId, $lowercaseSupplierId, $deliveryDate);

        return new NewDelivery(
            id: $lowercaseId,
            shopId: $shopId,
            supplierId: $lowercaseSupplierId,
            deliveryDate: $deliveryDate,
            reference: $reference,
            lines: $lines,
            payment: $payment,
        );
    }

    /**
     * @return list<NewDeliveryLine>
     */
    private function lines(): array
    {
        $validatedLines = $this->validated('lines');
        $lines = [];

        foreach ($validatedLines as $line) {
            $lineId = strtolower($line['id']);
            $productId = strtolower($line['product_id']);
            $lines[] = new NewDeliveryLine($lineId, $productId, $line['quantity'], $line['unit_price']);
        }

        return $lines;
    }

    private function payment(int $shopId, string $deliveryId, ?string $supplierId, string $deliveryDate): ?NewSupplierPayment
    {
        $payment = $this->validated('payment');

        if ($payment === null || $supplierId === null) {
            return null;
        }

        $paymentId = strtolower($payment['id']);
        $paymentMethod = PaymentMethod::from($payment['method']);

        return new NewSupplierPayment(
            id: $paymentId,
            shopId: $shopId,
            supplierId: $supplierId,
            deliveryId: $deliveryId,
            amount: $payment['amount'],
            method: $paymentMethod,
            paymentDate: $deliveryDate,
            reference: $payment['reference'] ?? null,
        );
    }
}
