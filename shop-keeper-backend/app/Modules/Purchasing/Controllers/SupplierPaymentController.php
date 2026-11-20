<?php

namespace App\Modules\Purchasing\Controllers;

use App\Modules\Purchasing\Actions\CancelSupplierPayment;
use App\Modules\Purchasing\Actions\FindSupplierPaymentOfShop;
use App\Modules\Purchasing\Actions\RecordSupplierPayment;
use App\Modules\Purchasing\Controllers\Requests\CancelRequest;
use App\Modules\Purchasing\Controllers\Requests\ListRequest;
use App\Modules\Purchasing\Controllers\Requests\RecordSupplierPaymentRequest;
use App\Modules\Purchasing\Controllers\Resources\SupplierPaymentResource;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Support\Created;
use App\Shared\CurrentShop;
use App\Shared\ShopClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class SupplierPaymentController
{
    public function index(ListRequest $request, CurrentShop $currentShop): AnonymousResourceCollection
    {
        $pageSize = $request->pageSize();
        $supplierId = $request->supplierId();
        $payments = SupplierPayment::query()
            ->ofShop($currentShop->shopId)
            ->ofSupplier($supplierId)
            ->withDetails()
            ->orderByDesc('payment_date')
            ->orderByDesc('sort_key')
            ->cursorPaginate($pageSize)
            ->withQueryString();

        return SupplierPaymentResource::collection($payments);
    }

    public function store(
        RecordSupplierPaymentRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordSupplierPayment $recordSupplierPayment,
    ): JsonResponse {
        $newPayment = $request->newSupplierPayment($currentShop->shopId, $shopClock);
        $created = $recordSupplierPayment->handle($newPayment);

        return $this->answer($created);
    }

    public function show(string $payment, CurrentShop $currentShop, FindSupplierPaymentOfShop $findSupplierPaymentOfShop): SupplierPaymentResource
    {
        $foundPayment = $findSupplierPaymentOfShop->handle($currentShop->shopId, $payment);
        Gate::authorize('view', $foundPayment);

        return new SupplierPaymentResource($foundPayment);
    }

    public function cancel(
        CancelRequest $request,
        string $payment,
        CurrentShop $currentShop,
        FindSupplierPaymentOfShop $findSupplierPaymentOfShop,
        CancelSupplierPayment $cancelSupplierPayment,
    ): JsonResponse {
        $foundPayment = $findSupplierPaymentOfShop->handle($currentShop->shopId, $payment);
        Gate::authorize('cancel', $foundPayment);

        $cancellationId = $request->cancellationId();
        $cancelled = $cancelSupplierPayment->handle($foundPayment, $cancellationId);

        return $this->answer($cancelled);
    }

    private function answer(Created $created): JsonResponse
    {
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new SupplierPaymentResource($created->record))->response()->setStatusCode($status);
    }
}
