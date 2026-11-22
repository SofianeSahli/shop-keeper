<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\StockAdjustmentKind;

final readonly class NewStockAdjustment
{
    /**
     * @param  list<NewStockAdjustmentLine>  $lines
     */
    public function __construct(
        public string $id,
        public int $shopId,
        public StockAdjustmentKind $kind,
        public string $adjustmentDate,
        public array $lines,
        public int $recordedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function productIds(): array
    {
        return array_map(fn (NewStockAdjustmentLine $line): string => $line->productId, $this->lines);
    }

    /**
     * @return list<string>
     */
    public function lineIds(): array
    {
        return array_map(fn (NewStockAdjustmentLine $line): string => $line->id, $this->lines);
    }

    public function isOpeningStock(): bool
    {
        return $this->kind === StockAdjustmentKind::OpeningStock;
    }
}
