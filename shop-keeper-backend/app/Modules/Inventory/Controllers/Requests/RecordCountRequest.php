<?php

namespace App\Modules\Inventory\Controllers\Requests;

use App\Modules\Inventory\Models\StockAdjustmentKind;
use App\Modules\Inventory\Support\NewStockAdjustmentLine;

final class RecordCountRequest extends StockAdjustmentRequest
{
    public const MAX_SHOWN_QUANTITY = 999_999_999;

    protected function kind(): StockAdjustmentKind
    {
        return StockAdjustmentKind::Count;
    }

    protected function lineRules(): array
    {
        return [
            'lines.*.counted_quantity' => ['required', 'integer:strict', 'min:0', 'max:'.self::MAX_QUANTITY],
            'lines.*.shown_quantity' => ['required', 'integer:strict', 'min:-'.self::MAX_SHOWN_QUANTITY, 'max:'.self::MAX_SHOWN_QUANTITY],
        ];
    }

    protected function lineMessages(): array
    {
        return [
            'lines.*.counted_quantity.required' => 'counted_quantity.required',
            'lines.*.counted_quantity.integer' => 'counted_quantity.integer',
            'lines.*.counted_quantity.min' => 'counted_quantity.negative',
            'lines.*.counted_quantity.max' => 'counted_quantity.too_high',
            'lines.*.shown_quantity.required' => 'shown_quantity.required',
            'lines.*.shown_quantity.integer' => 'shown_quantity.integer',
            'lines.*.shown_quantity.min' => 'shown_quantity.too_low',
            'lines.*.shown_quantity.max' => 'shown_quantity.too_high',
        ];
    }

    protected function newLine(string $lineId, string $productId, array $validatedLine): NewStockAdjustmentLine
    {
        return new NewStockAdjustmentLine(
            $lineId,
            $productId,
            countedQuantity: $validatedLine['counted_quantity'],
            shownQuantity: $validatedLine['shown_quantity'],
        );
    }
}
