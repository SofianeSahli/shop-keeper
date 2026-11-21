<?php

namespace App\Modules\Transactions\Controllers;

use App\Modules\Transactions\Actions\CancelTransaction;
use App\Modules\Transactions\Actions\FindTransactionOfShop;
use App\Modules\Transactions\Actions\ListTransactions;
use App\Modules\Transactions\Actions\RecordTransaction;
use App\Modules\Transactions\Controllers\Requests\CancelRequest;
use App\Modules\Transactions\Controllers\Requests\ListTransactionsRequest;
use App\Modules\Transactions\Controllers\Requests\RecordTransactionRequest;
use App\Modules\Transactions\Controllers\Resources\TransactionResource;
use App\Shared\CurrentShop;
use App\Shared\Records\Created;
use App\Shared\ShopClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class TransactionController
{
    public function index(ListTransactionsRequest $request, CurrentShop $currentShop, ListTransactions $listTransactions): AnonymousResourceCollection
    {
        $kind = $request->kind();
        $contactId = $request->contactId();
        $pageSize = $request->pageSize();
        $transactions = $listTransactions->handle($currentShop->shopId, $kind, $contactId, $pageSize);

        return TransactionResource::collection($transactions);
    }

    public function store(
        RecordTransactionRequest $request,
        CurrentShop $currentShop,
        ShopClock $shopClock,
        RecordTransaction $recordTransaction,
    ): JsonResponse {
        $accountId = $request->user()->id;
        $newTransaction = $request->newTransaction($currentShop->shopId, $accountId, $shopClock);
        $created = $recordTransaction->handle($newTransaction);

        return $this->answer($created);
    }

    public function show(string $transaction, CurrentShop $currentShop, FindTransactionOfShop $findTransactionOfShop): TransactionResource
    {
        $foundTransaction = $findTransactionOfShop->handle($currentShop->shopId, $transaction);
        Gate::authorize('view', $foundTransaction);

        return new TransactionResource($foundTransaction);
    }

    public function cancel(
        CancelRequest $request,
        string $transaction,
        CurrentShop $currentShop,
        FindTransactionOfShop $findTransactionOfShop,
        CancelTransaction $cancelTransaction,
    ): JsonResponse {
        $foundTransaction = $findTransactionOfShop->handle($currentShop->shopId, $transaction);
        Gate::authorize('cancel', $foundTransaction);

        $accountId = $request->user()->id;
        $newCancellation = $request->newCancellation($accountId);
        $cancelled = $cancelTransaction->handle($foundTransaction, $newCancellation);

        return $this->answer($cancelled);
    }

    private function answer(Created $created): JsonResponse
    {
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new TransactionResource($created->record))->response()->setStatusCode($status);
    }
}
