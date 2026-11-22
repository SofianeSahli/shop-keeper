<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\LossReason;
use App\Modules\Inventory\Models\StockAdjustmentKind;

final readonly class NewStockAdjustmentLine
{
    public function __construct(
        public string $id,
        public string $productId,
        public ?int $quantity = null,
        public ?int $countedQuantity = null,
        public ?int $shownQuantity = null,
        public ?LossReason $lossReason = null,
        public ?string $note = null,
    ) {}

    public function signedQuantity(StockAdjustmentKind $kind): int
    {
        return match ($kind) {
            StockAdjustmentKind::OpeningStock => $this->quantity,
            StockAdjustmentKind::Count => $this->countedQuantity - $this->shownQuantity,
            StockAdjustmentKind::Loss => -$this->quantity,
        };
    }
}
