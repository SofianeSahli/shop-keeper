<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Contacts\Actions\FindContactNamesOfShop;
use App\Modules\Transactions\Contracts\TransactionPayments;
use App\Modules\Transactions\Models\Transaction;
use Illuminate\Support\Collection;

final class AttachTransactionFigures
{
    public function __construct(
        private TransactionPayments $transactionPayments,
        private FindContactNamesOfShop $findContactNamesOfShop,
    ) {}

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    public function handle(int $shopId, Collection $transactions): void
    {
        $transactionIds = $transactions->map(fn (Transaction $transaction): string => $transaction->uuid())->all();
        $contactIds = $transactions->pluck('contact_id')->all();
        $amountPaidById = $this->transactionPayments->amountsPaid($shopId, $transactionIds);
        $contactNameById = $this->findContactNamesOfShop->handle($shopId, $contactIds);

        foreach ($transactions as $transaction) {
            $transactionId = $transaction->uuid();
            $transaction->amount_paid = $amountPaidById[$transactionId] ?? 0;
            $transaction->contact_name = $contactNameById[$transaction->contact_id] ?? null;
        }
    }
}
