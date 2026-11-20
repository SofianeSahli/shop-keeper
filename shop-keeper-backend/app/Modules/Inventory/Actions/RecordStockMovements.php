<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Actions\ChangeStockQuantities;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RecordStockMovements
{
    public function __construct(private ChangeStockQuantities $changeStockQuantities) {}

    /**
     * @param  list<NewStockMovement>  $movements
     */
    public function handle(int $shopId, StockMovementKind $kind, array $movements): void
    {
        DB::transaction(function () use ($shopId, $kind, $movements): void {
            $recordedAt = Carbon::now();
            $rows = array_map(
                fn (NewStockMovement $movement): array => $this->row($shopId, $kind, $movement, $recordedAt),
                $movements,
            );
            StockMovement::query()->insert($rows);

            $quantityByProductId = $this->quantityByProductId($movements);
            $this->changeStockQuantities->handle($shopId, $quantityByProductId);
        });
    }

    private function row(int $shopId, StockMovementKind $kind, NewStockMovement $movement, Carbon $recordedAt): array
    {
        $movementId = (string) Str::uuid7();

        return [
            'id' => BinaryUuid::toBytes($movementId),
            'shop_id' => $shopId,
            'shop_product_id' => BinaryUuid::toBytes($movement->productId),
            'kind' => $kind->value,
            'quantity' => $movement->quantity,
            'source_id' => BinaryUuid::toBytes($movement->sourceId),
            'created_at' => $recordedAt,
        ];
    }

    /**
     * @param  list<NewStockMovement>  $movements
     * @return array<string, int>
     */
    private function quantityByProductId(array $movements): array
    {
        $quantities = [];

        foreach ($movements as $movement) {
            $quantities[$movement->productId] = ($quantities[$movement->productId] ?? 0) + $movement->quantity;
        }

        return $quantities;
    }
}
