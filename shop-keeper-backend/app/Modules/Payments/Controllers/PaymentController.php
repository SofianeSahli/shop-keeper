<?php

namespace App\Modules\Payments\Controllers;

use App\Modules\Payments\Actions\CancelPayment;
use App\Modules\Payments\Actions\FindPaymentOfShop;
use App\Modules\Payments\Actions\ListPayments;
use App\Modules\Payments\Actions\RecordPayment;
use App\Modules\Payments\Controllers\Requests\CancelRequest;
use App\Modules\Payments\Controllers\Requests\ListPaymentsRequest;
use App\Modules\Payments\Controllers\Requests\RecordPaymentRequest;
use App\Modules\Payments\Controllers\Resources\PaymentResource;
use App\Shared\CurrentShop;
use App\Shared\Records\Created;
use App\Shared\ShopClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class PaymentController
{
    public function index(ListPaymentsRequest $request, CurrentShop $currentShop, ListPayments $listPayments): AnonymousResourceCollection
    {
        $contactId = $request->contactId();
        $transactionId = $request->transactionId();
        $pageSize = $request->pageSize();
        $payments = $listPayments->handle($currentShop->shopId, $contactId, $transactionId, $pageSize);

        return PaymentResource::collection($payments);
    }

    public function store(
        RecordPaymentRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordPayment $recordPayment,
    ): JsonResponse {
        $accountId = $request->user()->id;
        $newPayment = $request->newPayment($currentShop->shopId, $accountId, $shopClock);
        $created = $recordPayment->handle($newPayment);

        return $this->answer($created);
    }

    public function show(string $payment, CurrentShop $currentShop, FindPaymentOfShop $findPaymentOfShop): PaymentResource
    {
        $foundPayment = $findPaymentOfShop->handle($currentShop->shopId, $payment);
        Gate::authorize('view', $foundPayment);

        return new PaymentResource($foundPayment);
    }

    public function cancel(
        CancelRequest $request,
        string $payment,
        CurrentShop $currentShop,
        FindPaymentOfShop $findPaymentOfShop,
        CancelPayment $cancelPayment,
    ): JsonResponse {
        $foundPayment = $findPaymentOfShop->handle($currentShop->shopId, $payment);
        Gate::authorize('cancel', $foundPayment);

        $accountId = $request->user()->id;
        $newCancellation = $request->newCancellation($accountId);
        $cancelled = $cancelPayment->handle($foundPayment, $newCancellation);

        return $this->answer($cancelled);
    }

    private function answer(Created $created): JsonResponse
    {
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new PaymentResource($created->record))->response()->setStatusCode($status);
    }
}
