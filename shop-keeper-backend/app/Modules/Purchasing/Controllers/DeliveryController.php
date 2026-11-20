<?php

namespace App\Modules\Purchasing\Controllers;

use App\Modules\Purchasing\Actions\CancelDelivery;
use App\Modules\Purchasing\Actions\FindDeliveryOfShop;
use App\Modules\Purchasing\Actions\RecordDelivery;
use App\Modules\Purchasing\Controllers\Requests\CancelRequest;
use App\Modules\Purchasing\Controllers\Requests\ListRequest;
use App\Modules\Purchasing\Controllers\Requests\RecordDeliveryRequest;
use App\Modules\Purchasing\Controllers\Resources\DeliveryResource;
use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Support\Created;
use App\Shared\CurrentShop;
use App\Shared\ShopClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class DeliveryController
{
    public function index(ListRequest $request, CurrentShop $currentShop): AnonymousResourceCollection
    {
        $pageSize = $request->pageSize();
        $supplierId = $request->supplierId();
        $deliveries = Delivery::query()
            ->ofShop($currentShop->shopId)
            ->ofSupplier($supplierId)
            ->withDetails()
            ->orderByDesc('delivery_date')
            ->orderByDesc('sort_key')
            ->cursorPaginate($pageSize)
            ->withQueryString();

        return DeliveryResource::collection($deliveries);
    }

    public function store(
        RecordDeliveryRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordDelivery $recordDelivery,
    ): JsonResponse {
        $newDelivery = $request->newDelivery($currentShop->shopId, $shopClock);
        $created = $recordDelivery->handle($newDelivery);

        return $this->answer($created);
    }

    public function show(string $delivery, CurrentShop $currentShop, FindDeliveryOfShop $findDeliveryOfShop): DeliveryResource
    {
        $foundDelivery = $findDeliveryOfShop->handle($currentShop->shopId, $delivery);
        Gate::authorize('view', $foundDelivery);

        return new DeliveryResource($foundDelivery);
    }

    public function cancel(
        CancelRequest $request,
        string $delivery,
        CurrentShop $currentShop,
        FindDeliveryOfShop $findDeliveryOfShop,
        CancelDelivery $cancelDelivery,
    ): JsonResponse {
        $foundDelivery = $findDeliveryOfShop->handle($currentShop->shopId, $delivery);
        Gate::authorize('cancel', $foundDelivery);

        $cancellationId = $request->cancellationId();
        $cancelled = $cancelDelivery->handle($foundDelivery, $cancellationId);

        return $this->answer($cancelled);
    }

    private function answer(Created $created): JsonResponse
    {
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new DeliveryResource($created->record))->response()->setStatusCode($status);
    }
}
