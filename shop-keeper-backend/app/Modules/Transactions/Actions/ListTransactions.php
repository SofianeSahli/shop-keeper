<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionKind;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListTransactions
{
    public function __construct(private AttachTransactionFigures $attachTransactionFigures) {}

    public function handle(int $shopId, ?TransactionKind $kind, ?string $contactId, int $pageSize): CursorPaginator
    {
        $page = Transaction::query()
            ->ofShop($shopId)
            ->ofKind($kind)
            ->ofContact($contactId)
            ->withCancellation()
            ->newestFirst()
            ->cursorPaginate($pageSize)
            ->withQueryString();

        $transactions = $page->getCollection();
        $this->attachTransactionFigures->handle($shopId, $transactions);

        return $page;
    }
}
