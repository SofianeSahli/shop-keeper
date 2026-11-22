<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Actions\FindProductIdsOfShop;
use App\Modules\Inventory\Contracts\PurchaseLineSources;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\StockMovementDetails;
use App\Shared\Database\BinaryUuid;
use App\Shared\Errors\ApiException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class ListStockMovements
{
    public function __construct(
        private FindProductIdsOfShop $findProductIdsOfShop,
        private PurchaseLineSources $purchaseLineSources,
    ) {}

    public function handle(int $shopId, string $productId, int $pageSize): CursorPaginator
    {
        $productBytes = $this->productOfShop($shopId, $productId);
        $page = StockMovement::query()
            ->where('shop_id', $shopId)
            ->where('shop_product_id', $productBytes)
            ->orderByDesc('sequence')
            ->cursorPaginate($pageSize)
            ->withQueryString();

        $movements = $page->getCollection();

        if ($movements->isEmpty()) {
            return $page;
        }

        $this->attachStockAfter($productBytes, $movements);
        $this->attachAdjustmentDetails($movements);
        $this->attachPurchaseDetails($shopId, $movements);

        return $page;
    }

    private function productOfShop(int $shopId, string $productId): string
    {
        $isUuid = Str::isUuid($productId);
        $lowercaseId = strtolower($productId);
        $productIds = $isUuid ? [$lowercaseId] : [];
        $productIdsOfShop = $productIds === [] ? [] : $this->findProductIdsOfShop->handle($shopId, $productIds);

        if ($productIdsOfShop === []) {
            throw new ApiException('product.not_found', Response::HTTP_NOT_FOUND);
        }

        return BinaryUuid::toBytes($lowercaseId);
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    private function attachStockAfter(string $productBytes, Collection $movements): void
    {
        $oldest = $movements->last();
        $stockAfterOldest = (int) StockMovement::query()
            ->where('shop_product_id', $productBytes)
            ->where('sequence', '<=', $oldest->sequence)
            ->sum('quantity');
        $runningStock = $stockAfterOldest - $oldest->quantity;
        $oldestFirst = $movements->reverse();

        foreach ($oldestFirst as $movement) {
            $runningStock += $movement->quantity;
            $movement->stock_after = $runningStock;
        }
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    private function attachAdjustmentDetails(Collection $movements): void
    {
        $adjustmentMovements = $movements->filter(fn (StockMovement $movement): bool => $movement->kind->isAdjustment());

        if ($adjustmentMovements->isEmpty()) {
            return;
        }

        $lineBytes = $adjustmentMovements->map(fn (StockMovement $movement): string => BinaryUuid::toBytes($movement->source_id))->all();
        $lines = StockAdjustmentLine::query()->whereIn('id', $lineBytes)->get()->keyBy(fn (StockAdjustmentLine $line): string => $line->uuid());
        $adjustmentBytes = $lines->pluck('stock_adjustment_id')->unique()->values()->all();
        $adjustments = StockAdjustment::query()->whereIn('id', $adjustmentBytes)->get()->keyBy(fn (StockAdjustment $adjustment): string => $adjustment->getKey());

        foreach ($adjustmentMovements as $movement) {
            $line = $lines[$movement->source_id];
            $adjustment = $adjustments[$line->stock_adjustment_id];
            $details = StockMovementDetails::ofAdjustment($adjustment, $line);
            $movement->setRelation('details', $details);
        }
    }

    /**
     * @param  Collection<int, StockMovement>  $movements
     */
    private function attachPurchaseDetails(int $shopId, Collection $movements): void
    {
        $purchaseMovements = $movements->reject(fn (StockMovement $movement): bool => $movement->kind->isAdjustment());

        if ($purchaseMovements->isEmpty()) {
            return;
        }

        $lineIds = $purchaseMovements->pluck('source_id')->unique()->values()->all();
        $sources = $this->purchaseLineSources->describe($shopId, $lineIds);

        foreach ($purchaseMovements as $movement) {
            $source = $sources[$movement->source_id] ?? null;
            $details = $source === null ? new StockMovementDetails : StockMovementDetails::ofPurchase($movement->kind, $source);
            $movement->setRelation('details', $details);
        }
    }
}
