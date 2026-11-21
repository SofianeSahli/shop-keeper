<?php

namespace App\Modules\Payments\Support;

use App\Modules\Contacts\Contracts\ContactBalances;
use App\Modules\Payments\Actions\AttachPaymentContactNames;
use App\Modules\Payments\Controllers\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentDirection;
use App\Modules\Transactions\Actions\SumTransactionTotalsByContact;
use App\Modules\Transactions\Contracts\TransactionPayments;
use App\Modules\Transactions\Models\TransactionKind;
use App\Shared\Database\BinaryUuid;

final class PaymentFigures implements ContactBalances, TransactionPayments
{
    public function __construct(
        private AttachPaymentContactNames $attachPaymentContactNames,
        private SumTransactionTotalsByContact $sumTransactionTotalsByContact,
    ) {}

    public function balances(int $shopId, array $contactIds): array
    {
        if ($contactIds === []) {
            return [];
        }

        $purchaseTotals = $this->sumTransactionTotalsByContact->handle($shopId, TransactionKind::Purchase, $contactIds);
        $contactBytes = array_map(fn (string $contactId): string => BinaryUuid::toBytes($contactId), $contactIds);
        $paymentSums = Payment::query()
            ->ofShop($shopId)
            ->whereIn('contact_id', $contactBytes)
            ->notCancelled()
            ->groupBy('contact_id', 'direction')
            ->selectRaw('contact_id, direction, sum(amount) as direction_total')
            ->get();
        $balances = array_fill_keys($contactIds, 0);

        foreach ($purchaseTotals as $contactId => $purchaseTotal) {
            $balances[$contactId] += $purchaseTotal;
        }

        foreach ($paymentSums as $paymentSum) {
            $sign = $paymentSum->direction === PaymentDirection::Paid ? -1 : 1;
            $balances[$paymentSum->contact_id] += $sign * (int) $paymentSum->direction_total;
        }

        return $balances;
    }

    public function amountsPaid(int $shopId, array $transactionIds): array
    {
        if ($transactionIds === []) {
            return [];
        }

        $transactionBytes = array_map(fn (string $transactionId): string => BinaryUuid::toBytes($transactionId), $transactionIds);
        $sums = Payment::query()
            ->ofShop($shopId)
            ->whereIn('transaction_id', $transactionBytes)
            ->notCancelled()
            ->groupBy('transaction_id')
            ->selectRaw('transaction_id, sum(amount) as amount_paid')
            ->get();

        return $sums->mapWithKeys(fn (Payment $sum): array => [$sum->transaction_id => (int) $sum->amount_paid])->all();
    }

    public function hasActivePayments(string $transactionId): bool
    {
        return Payment::query()
            ->ofTransaction($transactionId)
            ->notCancelled()
            ->lockForUpdate()
            ->exists();
    }

    public function paymentsOf(int $shopId, string $transactionId): array
    {
        $payments = Payment::query()
            ->ofShop($shopId)
            ->ofTransaction($transactionId)
            ->withCancellation()
            ->newestFirst()
            ->get();
        $this->attachPaymentContactNames->handle($shopId, $payments);

        return $payments->map(fn (Payment $payment): array => (new PaymentResource($payment))->resolve())->all();
    }
}
