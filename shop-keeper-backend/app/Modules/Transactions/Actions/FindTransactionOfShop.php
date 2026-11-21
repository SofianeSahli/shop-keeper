<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Transactions\Contracts\TransactionPayments;
use App\Modules\Transactions\Models\Transaction;
use App\Shared\Errors\ApiException;
use Symfony\Component\HttpFoundation\Response;

final class FindTransactionOfShop
{
    public function __construct(
        private AttachTransactionFigures $attachTransactionFigures,
        private TransactionPayments $transactionPayments,
    ) {}

    public function handle(int $shopId, string $transactionId): Transaction
    {
        $transaction = Transaction::query()
            ->ofShop($shopId)
            ->whereUuid($transactionId)
            ->withCancellation()
            ->first() ?? throw new ApiException('transaction.not_found', Response::HTTP_NOT_FOUND);

        $transactions = collect([$transaction]);
        $this->attachTransactionFigures->handle($shopId, $transactions);

        $lines = $transaction->orderedLines();
        $foundId = $transaction->uuid();
        $payments = $this->transactionPayments->paymentsOf($shopId, $foundId);

        return $transaction->setRelation('lines', $lines)->setRelation('payments', $payments);
    }
}
