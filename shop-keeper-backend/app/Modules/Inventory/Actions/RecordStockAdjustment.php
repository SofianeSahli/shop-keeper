<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Actions\FindProductIdsOfShop;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Support\NewStockAdjustment;
use App\Modules\Inventory\Support\NewStockAdjustmentLine;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Shared\Database\BinaryUuid;
use App\Shared\Records\Created;
use App\Shared\Records\RecordWithSameId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordStockAdjustment
{
    public const OPENING_ALREADY_SET = 'product_id.opening_already_set';

    public function __construct(
        private FindProductIdsOfShop $findProductIdsOfShop,
        private RecordStockMovements $recordStockMovements,
        private FindStockAdjustmentOfShop $findStockAdjustmentOfShop,
    ) {}

    public function handle(NewStockAdjustment $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $adjustmentQuery = StockAdjustment::query();
            $adjustmentWithSameId = RecordWithSameId::find($adjustmentQuery, $new->id, $new->shopId);

            if ($adjustmentWithSameId !== null) {
                $this->refuseOtherKind($adjustmentWithSameId, $new);

                return false;
            }

            $this->refuseUsedLineIds($new, readLatest: false);
            $this->requireProductsOfShop($new);
            $this->refuseProductsWithOpeningStock($new, readLatest: false);

            $isInserted = $this->insertAdjustment($new);

            if (! $isInserted) {
                return false;
            }

            $this->insertLines($new);
            $this->recordStock($new);

            return true;
        });

        $adjustment = $this->findStockAdjustmentOfShop->handle($new->shopId, $new->id);

        return new Created($adjustment, $wasCreated);
    }

    private function refuseOtherKind(Model $adjustment, NewStockAdjustment $new): void
    {
        if ($adjustment->kind !== $new->kind) {
            throw ValidationException::withMessages(['id' => [RecordWithSameId::TAKEN]]);
        }
    }

    private function refuseUsedLineIds(NewStockAdjustment $new, bool $readLatest): void
    {
        $lineIds = $new->lineIds();
        $lineBytes = array_map(fn (string $lineId): string => BinaryUuid::toBytes($lineId), $lineIds);
        $usedLineIds = StockAdjustmentLine::query()
            ->whereIn('id', $lineBytes)
            ->when($readLatest, fn ($query) => $query->lockForUpdate())
            ->pluck('id')
            ->all();

        $this->throwForLines($new, fn (NewStockAdjustmentLine $line): bool => in_array($line->id, $usedLineIds, true), 'id', RecordWithSameId::TAKEN);
    }

    private function requireProductsOfShop(NewStockAdjustment $new): void
    {
        $productIds = $new->productIds();
        $productIdsOfShop = $this->findProductIdsOfShop->handle($new->shopId, $productIds);

        $this->throwForLines($new, fn (NewStockAdjustmentLine $line): bool => ! in_array($line->productId, $productIdsOfShop, true), 'product_id', 'product_id.unknown');
    }

    private function refuseProductsWithOpeningStock(NewStockAdjustment $new, bool $readLatest): void
    {
        if (! $new->isOpeningStock()) {
            return;
        }

        $productIds = $new->productIds();
        $productBytes = array_map(fn (string $productId): string => BinaryUuid::toBytes($productId), $productIds);
        $openedBytes = StockAdjustmentLine::query()
            ->whereIn('opening_product_id', $productBytes)
            ->when($readLatest, fn ($query) => $query->lockForUpdate())
            ->pluck('opening_product_id')
            ->all();
        $openedProductIds = array_map(fn (string $bytes): string => BinaryUuid::fromBytes($bytes), $openedBytes);

        $this->throwForLines($new, fn (NewStockAdjustmentLine $line): bool => in_array($line->productId, $openedProductIds, true), 'product_id', self::OPENING_ALREADY_SET);
    }

    /**
     * @param  callable(NewStockAdjustmentLine): bool  $isWrong
     */
    private function throwForLines(NewStockAdjustment $new, callable $isWrong, string $field, string $code): void
    {
        $errors = [];

        foreach ($new->lines as $index => $line) {
            if ($isWrong($line)) {
                $errors["lines.{$index}.{$field}"] = [$code];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function insertAdjustment(NewStockAdjustment $new): bool
    {
        $lineCount = count($new->lines);

        try {
            StockAdjustment::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'kind' => $new->kind,
                'adjustment_date' => $new->adjustmentDate,
                'line_count' => $lineCount,
                'created_by' => $new->recordedBy,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $adjustmentQuery = StockAdjustment::query();
            $winner = RecordWithSameId::find($adjustmentQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                $this->refuseOtherKind($winner, $new);

                return false;
            }

            throw $lostRace;
        }

        return true;
    }

    private function insertLines(NewStockAdjustment $new): void
    {
        $rows = $this->lineRows($new);

        try {
            StockAdjustmentLine::query()->insert($rows);
        } catch (UniqueConstraintViolationException $lostRace) {
            $this->refuseProductsWithOpeningStock($new, readLatest: true);
            $this->refuseUsedLineIds($new, readLatest: true);

            throw $lostRace;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineRows(NewStockAdjustment $new): array
    {
        $recordedAt = Carbon::now();
        $adjustmentBytes = BinaryUuid::toBytes($new->id);
        $rows = [];

        foreach ($new->lines as $index => $line) {
            $lineBytes = BinaryUuid::toBytes($line->id);
            $productBytes = BinaryUuid::toBytes($line->productId);
            $signedQuantity = $line->signedQuantity($new->kind);
            $rows[] = [
                'id' => $lineBytes,
                'stock_adjustment_id' => $adjustmentBytes,
                'position' => $index + 1,
                'shop_product_id' => $productBytes,
                'quantity' => $signedQuantity,
                'counted_quantity' => $line->countedQuantity,
                'shown_quantity' => $line->shownQuantity,
                'loss_reason' => $line->lossReason?->value,
                'note' => $line->note,
                'opening_product_id' => $new->isOpeningStock() ? $productBytes : null,
                'created_at' => $recordedAt,
            ];
        }

        return $rows;
    }

    private function recordStock(NewStockAdjustment $new): void
    {
        $movements = array_map(
            function (NewStockAdjustmentLine $line) use ($new): NewStockMovement {
                $signedQuantity = $line->signedQuantity($new->kind);

                return new NewStockMovement($line->productId, $signedQuantity, $line->id);
            },
            $new->lines,
        );
        $movementKind = $new->kind->movementKind();

        $this->recordStockMovements->handle($new->shopId, $movementKind, $movements);
    }
}
