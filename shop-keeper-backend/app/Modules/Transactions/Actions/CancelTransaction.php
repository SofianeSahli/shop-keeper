<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Inventory\Actions\RecordStockMovements;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Modules\Transactions\Contracts\TransactionPayments;
use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionCancellation;
use App\Modules\Transactions\Models\TransactionLine;
use App\Shared\Errors\ApiException;
use App\Shared\Records\CancelOnce;
use App\Shared\Records\Created;
use App\Shared\Records\NewCancellation;
use Symfony\Component\HttpFoundation\Response;

final class CancelTransaction
{
    private const RECORD_COLUMN = 'transaction_id';

    public function __construct(
        private CancelOnce $cancelOnce,
        private TransactionPayments $transactionPayments,
        private RecordStockMovements $recordStockMovements,
        private FindTransactionOfShop $findTransactionOfShop,
    ) {}

    public function handle(Transaction $transaction, NewCancellation $new): Created
    {
        $transactionId = $transaction->uuid();
        $cancellation = new TransactionCancellation([
            'id' => $new->id,
            'shop_id' => $transaction->shop_id,
            self::RECORD_COLUMN => $transactionId,
            'reason' => $new->reason,
            'cancelled_by' => $new->cancelledBy,
        ]);

        $cancelled = $this->cancelOnce->handle($transaction, $cancellation, self::RECORD_COLUMN, function (Transaction $locked): void {
            $this->refuseIfPaid($locked);
            $this->takeBackStock($locked);
        });

        $cancelledTransaction = $this->findTransactionOfShop->handle($transaction->shop_id, $transactionId);

        return new Created($cancelledTransaction, $cancelled->wasCreated);
    }

    private function refuseIfPaid(Transaction $locked): void
    {
        $lockedId = $locked->uuid();
        $hasActivePayments = $this->transactionPayments->hasActivePayments($lockedId);

        if ($hasActivePayments) {
            throw new ApiException('transaction.has_payments', Response::HTTP_CONFLICT);
        }
    }

    private function takeBackStock(Transaction $locked): void
    {
        $lines = $locked->orderedLines();
        $movements = $lines
            ->map(function (TransactionLine $line): NewStockMovement {
                $lineId = $line->uuid();

                return new NewStockMovement($line->shop_product_id, -$line->quantity, $lineId);
            })
            ->all();

        $this->recordStockMovements->handle($locked->shop_id, StockMovementKind::PurchaseCancellation, $movements);
    }
}
