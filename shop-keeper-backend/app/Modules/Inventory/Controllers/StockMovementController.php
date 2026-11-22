<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Actions\ListStockMovements;
use App\Modules\Inventory\Controllers\Requests\ListStockMovementsRequest;
use App\Modules\Inventory\Controllers\Resources\StockMovementResource;
use App\Shared\CurrentShop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class StockMovementController
{
    public function __invoke(
        ListStockMovementsRequest $request,
        string $product,
        CurrentShop $currentShop,
        ListStockMovements $listStockMovements,
    ): AnonymousResourceCollection {
        $pageSize = $request->pageSize();
        $movements = $listStockMovements->handle($currentShop->shopId, $product, $pageSize);

        return StockMovementResource::collection($movements);
    }
}
