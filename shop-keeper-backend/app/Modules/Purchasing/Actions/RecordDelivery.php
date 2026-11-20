<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Catalog\Actions\FindProductIdsOfShop;
use App\Modules\Inventory\Actions\RecordStockMovements;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Models\DeliveryLine;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\Created;
use App\Modules\Purchasing\Support\NewDelivery;
use App\Modules\Purchasing\Support\NewDeliveryLine;
use App\Modules\Purchasing\Support\RecordWithSameId;
use App\Shared\Database\BinaryUuid;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordDelivery
{
    public const MAX_TOTAL_MILLIMES = 9_007_199_254_740_991;

    public function __construct(
        private FindProductIdsOfShop $findProductIdsOfShop,
        private RecordSupplierPayment $recordSupplierPayment,
        private RecordStockMovements $recordStockMovements,
        private FindDeliveryOfShop $findDeliveryOfShop,
    ) {}

    public function handle(NewDelivery $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $deliveryQuery = Delivery::query();
            $deliveryWithSameId = RecordWithSameId::find($deliveryQuery, $new->id, $new->shopId);

            if ($deliveryWithSameId !== null) {
                return false;
            }

            $this->refuseUsedIds($new);
            $this->requireSupplierOfShop($new);
            $this->requireProductsOfShop($new);
            $total = $this->checkedTotal($new);

            $isInserted = $this->insertDelivery($new, $total);

            if (! $isInserted) {
                return false;
            }

            $this->insertLines($new);
            $this->recordPayment($new);
            $this->recordStock($new);

            return true;
        });

        $delivery = $this->findDeliveryOfShop->handle($new->shopId, $new->id);

        return new Created($delivery, $wasCreated);
    }

    private function refuseUsedIds(NewDelivery $new): void
    {
        $lineBytes = array_map(fn (NewDeliveryLine $line): string => BinaryUuid::toBytes($line->id), $new->lines);
        $usedLineIds = DeliveryLine::query()->whereIn('id', $lineBytes)->pluck('id')->all();
        $errors = [];

        foreach ($new->lines as $index => $line) {
            if (in_array($line->id, $usedLineIds, true)) {
                $errors["lines.{$index}.id"] = [RecordWithSameId::TAKEN];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($new->payment !== null) {
            $paymentQuery = SupplierPayment::query();
            RecordWithSameId::refuseIfUsed($paymentQuery, $new->payment->id, 'payment.id');
        }
    }

    private function requireSupplierOfShop(NewDelivery $new): void
    {
        if ($new->supplierId === null) {
            return;
        }

        $isSupplierOfShop = Supplier::query()->ofShop($new->shopId)->whereUuid($new->supplierId)->exists();

        if (! $isSupplierOfShop) {
            throw ValidationException::withMessages(['supplier_id' => ['supplier_id.unknown']]);
        }
    }

    private function requireProductsOfShop(NewDelivery $new): void
    {
        $productIds = $new->productIds();
        $productIdsOfShop = $this->findProductIdsOfShop->handle($new->shopId, $productIds);
        $errors = [];

        foreach ($new->lines as $index => $line) {
            if (! in_array($line->productId, $productIdsOfShop, true)) {
                $errors["lines.{$index}.product_id"] = ['product_id.unknown'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function checkedTotal(NewDelivery $new): int
    {
        $total = $new->total();

        if ($total > self::MAX_TOTAL_MILLIMES) {
            throw ValidationException::withMessages(['lines' => ['lines.total_too_high']]);
        }

        return $total;
    }

    private function insertDelivery(NewDelivery $new, int $total): bool
    {
        $lineCount = count($new->lines);

        try {
            Delivery::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'supplier_id' => $new->supplierId,
                'delivery_date' => $new->deliveryDate,
                'reference' => $new->reference,
                'total' => $total,
                'line_count' => $lineCount,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $deliveryQuery = Delivery::query();
            $winner = RecordWithSameId::find($deliveryQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            throw $lostRace;
        }

        return true;
    }

    private function insertLines(NewDelivery $new): void
    {
        $recordedAt = Carbon::now();
        $deliveryBytes = BinaryUuid::toBytes($new->id);
        $rows = [];

        foreach ($new->lines as $index => $line) {
            $rows[] = [
                'id' => BinaryUuid::toBytes($line->id),
                'shop_id' => $new->shopId,
                'delivery_id' => $deliveryBytes,
                'position' => $index + 1,
                'shop_product_id' => BinaryUuid::toBytes($line->productId),
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'amount' => $line->amount(),
                'created_at' => $recordedAt,
            ];
        }

        DeliveryLine::query()->insert($rows);
    }

    private function recordPayment(NewDelivery $new): void
    {
        if ($new->payment !== null) {
            $this->recordSupplierPayment->handle($new->payment);
        }
    }

    private function recordStock(NewDelivery $new): void
    {
        $movements = array_map(
            fn (NewDeliveryLine $line): NewStockMovement => new NewStockMovement($line->productId, $line->quantity, $line->id),
            $new->lines,
        );

        $this->recordStockMovements->handle($new->shopId, StockMovementKind::Delivery, $movements);
    }
}
