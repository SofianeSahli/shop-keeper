<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionCancellation;
use App\Modules\Transactions\Support\TransactionForPayment;

final class LockTransactionForPayment
{
    public function handle(int $shopId, string $transactionId): ?TransactionForPayment
    {
        $transaction = Transaction::query()
            ->ofShop($shopId)
            ->whereUuid($transactionId)
            ->lockForUpdate()
            ->first();

        if ($transaction === null) {
            return null;
        }

        $transactionBytes = $transaction->getKey();
        $isCancelled = TransactionCancellation::query()->where('transaction_id', $transactionBytes)->lockForUpdate()->exists();
        $lockedId = $transaction->uuid();
        $paymentDirection = $transaction->kind->paymentDirection();

        return new TransactionForPayment(
            id: $lockedId,
            contactId: $transaction->contact_id,
            total: $transaction->total,
            paymentDirection: $paymentDirection,
            isCancelled: $isCancelled,
        );
    }
}
