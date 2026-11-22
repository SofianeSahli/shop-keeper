<?php

namespace App\Modules\Inventory\Support;

use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Models\StockMovementKind;

final readonly class StockMovementDetails
{
    public function __construct(
        public ?string $date = null,
        public ?int $recordedBy = null,
        public ?string $adjustmentId = null,
        public ?string $transactionId = null,
        public ?int $countedQuantity = null,
        public ?int $shownQuantity = null,
        public ?string $reason = null,
        public ?string $note = null,
    ) {}

    public static function ofAdjustment(StockAdjustment $adjustment, StockAdjustmentLine $line): self
    {
        $adjustmentId = $adjustment->uuid();

        return new self(
            date: $adjustment->adjustment_date,
            recordedBy: $adjustment->created_by,
            adjustmentId: $adjustmentId,
            countedQuantity: $line->counted_quantity,
            shownQuantity: $line->shown_quantity,
            reason: $line->loss_reason?->value,
            note: $line->note,
        );
    }

    public static function ofPurchase(StockMovementKind $kind, PurchaseLineSource $source): self
    {
        if ($kind === StockMovementKind::PurchaseCancellation) {
            return new self(date: $source->cancelledOn, recordedBy: $source->cancelledBy, transactionId: $source->transactionId);
        }

        return new self(date: $source->transactionDate, recordedBy: $source->recordedBy, transactionId: $source->transactionId);
    }
}
