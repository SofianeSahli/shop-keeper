<?php

namespace App\Modules\Inventory\Controllers\Requests;

use App\Modules\Inventory\Models\StockAdjustmentKind;
use App\Modules\Inventory\Support\NewStockAdjustment;
use App\Modules\Inventory\Support\NewStockAdjustmentLine;
use App\Shared\ShopClock;
use Illuminate\Foundation\Http\FormRequest;

abstract class StockAdjustmentRequest extends FormRequest
{
    public const MAX_LINES = 200;

    public const MAX_QUANTITY = 999_999;

    abstract protected function kind(): StockAdjustmentKind;

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected function lineRules(): array;

    /**
     * @return array<string, string>
     */
    abstract protected function lineMessages(): array;

    abstract protected function newLine(string $lineId, string $productId, array $validatedLine): NewStockAdjustmentLine;

    public function rules(ShopClock $shopClock): array
    {
        $today = $shopClock->todayAsDate();
        $lineRules = $this->lineRules();

        return [
            'id' => ['required', 'string', 'uuid'],
            'adjustment_date' => ['nullable', 'bail', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'lines' => ['required', 'array', 'max:'.self::MAX_LINES],
            'lines.*' => ['array'],
            'lines.*.id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            'lines.*.product_id' => ['required', 'string', 'uuid', 'distinct:ignore_case'],
            ...$lineRules,
        ];
    }

    public function messages(): array
    {
        $lineMessages = $this->lineMessages();

        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'adjustment_date.date_format' => 'adjustment_date.invalid',
            'adjustment_date.before_or_equal' => 'adjustment_date.in_future',
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
            ...$lineMessages,
        ];
    }

    public function newStockAdjustment(int $shopId, int $accountId, ShopClock $shopClock): NewStockAdjustment
    {
        $id = $this->validated('id');
        $lowercaseId = strtolower($id);
        $kind = $this->kind();
        $today = $shopClock->todayAsDate();
        $adjustmentDate = $this->validated('adjustment_date') ?? $today;
        $lines = $this->lines();

        return new NewStockAdjustment(
            id: $lowercaseId,
            shopId: $shopId,
            kind: $kind,
            adjustmentDate: $adjustmentDate,
            lines: $lines,
            recordedBy: $accountId,
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function quantityRules(): array
    {
        return ['lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:'.self::MAX_QUANTITY]];
    }

    /**
     * @return array<string, string>
     */
    protected function quantityMessages(): array
    {
        return [
            'lines.*.quantity.required' => 'quantity.required',
            'lines.*.quantity.integer' => 'quantity.integer',
            'lines.*.quantity.min' => 'quantity.too_low',
            'lines.*.quantity.max' => 'quantity.too_high',
        ];
    }

    /**
     * @return list<NewStockAdjustmentLine>
     */
    private function lines(): array
    {
        $validatedLines = $this->validated('lines');
        $lines = [];

        foreach ($validatedLines as $validatedLine) {
            $lineId = strtolower($validatedLine['id']);
            $productId = strtolower($validatedLine['product_id']);
            $lines[] = $this->newLine($lineId, $productId, $validatedLine);
        }

        return $lines;
    }
}
