<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Contacts\Actions\FindContactIdsOfShop;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\NewPayment;
use App\Modules\Transactions\Actions\LockTransactionForPayment;
use App\Modules\Transactions\Support\TransactionForPayment;
use App\Shared\Records\Created;
use App\Shared\Records\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordPayment
{
    public function __construct(
        private FindContactIdsOfShop $findContactIdsOfShop,
        private LockTransactionForPayment $lockTransactionForPayment,
        private FindPaymentOfShop $findPaymentOfShop,
    ) {}

    public function handle(NewPayment $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $paymentQuery = Payment::query();
            $paymentWithSameId = RecordWithSameId::find($paymentQuery, $new->id, $new->shopId);

            if ($paymentWithSameId !== null) {
                return false;
            }

            $this->requireContactOfShop($new);
            $contactId = $new->contactId;

            if ($new->transactionId !== null) {
                $transaction = $this->lockedTransaction($new);
                $this->requireMatch($new, $transaction);
                $contactId = $transaction->contactId;
            }

            return $this->insert($new, $contactId);
        });

        $payment = $this->findPaymentOfShop->handle($new->shopId, $new->id);

        return new Created($payment, $wasCreated);
    }

    private function requireContactOfShop(NewPayment $new): void
    {
        if ($new->contactId === null) {
            return;
        }

        $contactIds = [$new->contactId];
        $contactIdsOfShop = $this->findContactIdsOfShop->handle($new->shopId, $contactIds);

        if ($contactIdsOfShop === []) {
            throw ValidationException::withMessages(['contact_id' => ['contact_id.unknown']]);
        }
    }

    private function lockedTransaction(NewPayment $new): TransactionForPayment
    {
        $transaction = $this->lockTransactionForPayment->handle($new->shopId, $new->transactionId);

        if ($transaction === null) {
            throw ValidationException::withMessages(['transaction_id' => ['transaction_id.unknown']]);
        }

        if ($transaction->isCancelled) {
            throw ValidationException::withMessages(['transaction_id' => ['transaction_id.cancelled']]);
        }

        return $transaction;
    }

    private function requireMatch(NewPayment $new, TransactionForPayment $transaction): void
    {
        if ($new->contactId !== null && $new->contactId !== $transaction->contactId) {
            throw ValidationException::withMessages(['contact_id' => ['contact_id.mismatch']]);
        }

        if ($new->direction->value !== $transaction->paymentDirection) {
            throw ValidationException::withMessages(['direction' => ['direction.mismatch']]);
        }

        $amountPaid = Payment::query()
            ->ofTransaction($transaction->id)
            ->notCancelled()
            ->lockForUpdate()
            ->sum('amount');
        $amountLeft = $transaction->total - (int) $amountPaid;

        if ($new->amount > $amountLeft) {
            throw ValidationException::withMessages(['amount' => ['amount.exceeds_left']]);
        }
    }

    private function insert(NewPayment $new, ?string $contactId): bool
    {
        try {
            Payment::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'direction' => $new->direction,
                'contact_id' => $contactId,
                'transaction_id' => $new->transactionId,
                'amount' => $new->amount,
                'method' => $new->method,
                'payment_date' => $new->paymentDate,
                'reference' => $new->reference,
                'created_by' => $new->recordedBy,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $paymentQuery = Payment::query();
            $winner = RecordWithSameId::find($paymentQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            throw $lostRace;
        }

        return true;
    }
}
