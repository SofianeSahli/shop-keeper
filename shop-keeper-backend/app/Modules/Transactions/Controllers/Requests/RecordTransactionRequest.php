<?php

namespace App\Modules\Transactions\Controllers\Requests;

use App\Modules\Catalog\Controllers\Requests\Rules\PriceInMillimes;
use App\Modules\Transactions\Models\TransactionKind;
use App\Modules\Transactions\Support\NewTransaction;
use App\Modules\Transactions\Support\NewTransactionLine;
use App\Shared\ShopClock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class RecordTransactionRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public const MAX_QUANTITY = 999_999;

    public const MAX_REFERENCE_LENGTH = 50;

    public function rules(ShopClock $shopClock): array
    {
        $today = $shopClock->todayAsDate();
        $transactionKind = Rule::enum(TransactionKind::class);
        $unitPrice = PriceInMillimes::rules(required: true);

        return [
            'id' => ['required', 'string', 'uuid'],
            'kind' => ['required', $transactionKind],
            'contact_id' => ['nullable', 'string', 'uuid'],
            'transaction_date' => ['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reference' => ['nullable', 'string', 'max:'.self::MAX_REFERENCE_LENGTH],
            'lines' => ['required', 'array', 'max:'.self::MAX_LINES],
            'lines.*' => ['array'],
            'lines.*.id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            'lines.*.product_id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            'lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_QUANTITY],
            'lines.*.unit_price' => $unitPrice,
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'kind.required' => 'kind.required',
            'kind.'.Enum::class => 'kind.invalid',
            'contact_id.string' => 'contact_id.invalid',
            'contact_id.uuid' => 'contact_id.invalid',
            'transaction_date.date_format' => 'transaction_date.invalid',
            'transaction_date.before_or_equal' => 'transaction_date.in_future',
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
        ];
    }

    public function newTransaction(int $shopId, int $accountId, ShopClock $shopClock): NewTransaction
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $kind = $this->validated('kind');
        $transactionKind = TransactionKind::from($kind);
        $contactId = $this->validated('contact_id');
        $lowercaseContactId = $contactId === null ? null : strtolower($contactId);
        $today = $shopClock->todayAsDate();
        $transactionDate = $this->validated('transaction_date') ?? $today;
        $reference = $this->validated('reference');
        $lines = $this->lines();

        return new NewTransaction(
            id: $lowercaseId,
            shopId: $shopId,
            kind: $transactionKind,
            contactId: $lowercaseContactId,
            transactionDate: $transactionDate,
            reference: $reference,
            lines: $lines,
            recordedBy: $accountId,
        );
    }

    /**
     * @return list<NewTransactionLine>
     */
    private function lines(): array
    {
        $validatedLines = $this->validated('lines');
        $lines = [];

        foreach ($validatedLines as $line) {
            $lineId = strtolower($line['id']);
            $productId = strtolower($line['product_id']);
            $lines[] = new NewTransactionLine($lineId, $productId, $line['quantity'], $line['unit_price']);
        }

        return $lines;
    }
}
