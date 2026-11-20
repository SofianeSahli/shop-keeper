<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Inventory\Actions\RecordStockMovements;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Models\DeliveryCancellation;
use App\Modules\Purchasing\Models\DeliveryLine;
use App\Modules\Purchasing\Support\Created;
use App\Modules\Purchasing\Support\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelDelivery
{
    public function __construct(
        private RecordStockMovements $recordStockMovements,
        private FindDeliveryOfShop $findDeliveryOfShop,
    ) {}

    public function handle(Delivery $delivery, string $cancellationId): Created
    {
        $deliveryId = $delivery->uuid();

        $wasCancelled = DB::transaction(function () use ($delivery, $deliveryId, $cancellationId): bool {
            $locked = Delivery::query()->whereUuid($deliveryId)->lockForUpdate()->firstOrFail();
            $deliveryBytes = $locked->getKey();
            $isAlreadyCancelled = DeliveryCancellation::query()->where('delivery_id', $deliveryBytes)->exists();

            if ($isAlreadyCancelled) {
                return false;
            }

            $cancellationQuery = DeliveryCancellation::query();
            RecordWithSameId::refuseIfUsed($cancellationQuery, $cancellationId, 'id');
            $this->insertCancellation($delivery, $deliveryId, $cancellationId);
            $this->takeBackStock($delivery, $deliveryBytes);

            return true;
        });

        $cancelledDelivery = $this->findDeliveryOfShop->handle($delivery->shop_id, $deliveryId);

        return new Created($cancelledDelivery, $wasCancelled);
    }

    private function insertCancellation(Delivery $delivery, string $deliveryId, string $cancellationId): void
    {
        try {
            DeliveryCancellation::query()->create([
                'id' => $cancellationId,
                'shop_id' => $delivery->shop_id,
                'delivery_id' => $deliveryId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['id' => [RecordWithSameId::TAKEN]]);
        }
    }

    private function takeBackStock(Delivery $delivery, string $deliveryBytes): void
    {
        $lines = DeliveryLine::query()->where('delivery_id', $deliveryBytes)->orderBy('position')->get();
        $movements = $lines
            ->map(function (DeliveryLine $line): NewStockMovement {
                $lineId = $line->uuid();

                return new NewStockMovement($line->shop_product_id, -$line->quantity, $lineId);
            })
            ->all();

        $this->recordStockMovements->handle($delivery->shop_id, StockMovementKind::DeliveryCancellation, $movements);
    }
}
