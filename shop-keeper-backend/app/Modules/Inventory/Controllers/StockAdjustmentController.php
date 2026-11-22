<?php

namespace App\Modules\Inventory\Controllers;

use App\Modules\Inventory\Actions\RecordStockAdjustment;
use App\Modules\Inventory\Controllers\Requests\RecordCountRequest;
use App\Modules\Inventory\Controllers\Requests\RecordLossRequest;
use App\Modules\Inventory\Controllers\Requests\RecordOpeningStockRequest;
use App\Modules\Inventory\Controllers\Requests\StockAdjustmentRequest;
use App\Modules\Inventory\Controllers\Resources\StockAdjustmentResource;
use App\Shared\CurrentShop;
use App\Shared\ShopClock;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StockAdjustmentController
{
    public function openings(
        RecordOpeningStockRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordStockAdjustment $recordStockAdjustment,
    ): JsonResponse {
        return $this->record($request, $currentShop, $shopClock, $recordStockAdjustment);
    }

    public function counts(
        RecordCountRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordStockAdjustment $recordStockAdjustment,
    ): JsonResponse {
        return $this->record($request, $currentShop, $shopClock, $recordStockAdjustment);
    }

    public function losses(
        RecordLossRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordStockAdjustment $recordStockAdjustment,
    ): JsonResponse {
        return $this->record($request, $currentShop, $shopClock, $recordStockAdjustment);
    }

    private function record(
        StockAdjustmentRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordStockAdjustment $recordStockAdjustment,
    ): JsonResponse {
        $accountId = $request->user()->id;
        $newAdjustment = $request->newStockAdjustment($currentShop->shopId, $accountId, $shopClock);
        $created = $recordStockAdjustment->handle($newAdjustment);
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new StockAdjustmentResource($created->record))->response()->setStatusCode($status);
    }
}
