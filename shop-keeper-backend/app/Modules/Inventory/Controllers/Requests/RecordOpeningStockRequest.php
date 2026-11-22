<?php

namespace App\Modules\Inventory\Controllers\Requests;

use App\Modules\Inventory\Models\StockAdjustmentKind;
use App\Modules\Inventory\Support\NewStockAdjustmentLine;

final class RecordOpeningStockRequest extends StockAdjustmentRequest
{
    protected function kind(): StockAdjustmentKind
    {
        return StockAdjustmentKind::OpeningStock;
    }

    protected function lineRules(): array
    {
        return $this->quantityRules();
    }

    protected function lineMessages(): array
    {
        return $this->quantityMessages();
    }

    protected function newLine(string $lineId, string $productId, array $validatedLine): NewStockAdjustmentLine
    {
        return new NewStockAdjustmentLine($lineId, $productId, quantity: $validatedLine['quantity']);
    }
}
