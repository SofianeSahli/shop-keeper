<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListPayments
{
    public function __construct(private AttachPaymentContactNames $attachPaymentContactNames) {}

    public function handle(int $shopId, ?string $contactId, ?string $transactionId, int $pageSize): CursorPaginator
    {
        $page = Payment::query()
            ->ofShop($shopId)
            ->ofContact($contactId)
            ->ofTransaction($transactionId)
            ->withCancellation()
            ->newestFirst()
            ->cursorPaginate($pageSize)
            ->withQueryString();

        $payments = $page->getCollection();
        $this->attachPaymentContactNames->handle($shopId, $payments);

        return $page;
    }
}
