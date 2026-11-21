<?php

namespace App\Modules\Transactions\Actions;

use App\Modules\Catalog\Actions\FindProductIdsOfShop;
use App\Modules\Contacts\Actions\FindContactIdsOfShop;
use App\Modules\Inventory\Actions\RecordStockMovements;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionLine;
use App\Modules\Transactions\Support\NewTransaction;
use App\Modules\Transactions\Support\NewTransactionLine;
use App\Shared\Database\BinaryUuid;
use App\Shared\Records\Created;
use App\Shared\Records\RecordWithSameId;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RecordTransaction
{
    public const MAX_TOTAL_MILLIMES = 9_007_199_254_740_991;

    public function __construct(
        private FindContactIdsOfShop $findContactIdsOfShop,
        private FindProductIdsOfShop $findProductIdsOfShop,
        private RecordStockMovements $recordStockMovements,
        private FindTransactionOfShop $findTransactionOfShop,
    ) {}

    public function handle(NewTransaction $new): Created
    {
        $wasCreated = DB::transaction(function () use ($new): bool {
            $transactionQuery = Transaction::query();
            $transactionWithSameId = RecordWithSameId::find($transactionQuery, $new->id, $new->shopId);

            if ($transactionWithSameId !== null) {
                return false;
            }

            $this->refuseUsedLineIds($new);
            $this->requireContactOfShop($new);
            $this->requireProductsOfShop($new);
            $total = $this->checkedTotal($new);

            $isInserted = $this->insertTransaction($new, $total);

            if (! $isInserted) {
                return false;
            }

            $this->insertLines($new);
            $this->recordStock($new);

            return true;
        });

        $transaction = $this->findTransactionOfShop->handle($new->shopId, $new->id);

        return new Created($transaction, $wasCreated);
    }

    private function refuseUsedLineIds(NewTransaction $new): void
    {
        $lineBytes = array_map(fn (NewTransactionLine $line): string => BinaryUuid::toBytes($line->id), $new->lines);
        $usedLineIds = TransactionLine::query()->whereIn('id', $lineBytes)->pluck('id')->all();
        $errors = [];

        foreach ($new->lines as $index => $line) {
            if (in_array($line->id, $usedLineIds, true)) {
                $errors["lines.{$index}.id"] = [RecordWithSameId::TAKEN];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function requireContactOfShop(NewTransaction $new): void
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

    private function requireProductsOfShop(NewTransaction $new): void
    {
        $productIds = $new->productIds();
        $productIdsOfShop = $this->findProductIdsOfShop->handle($new->shopId, $productIds);
        $errors = [];

        foreach ($new->lines as $index => $line) {
            if (! in_array($line->productId, $productIdsOfShop, true)) {
                $errors["lines.{$index}.product_id"] = ['product_id.unknown'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function checkedTotal(NewTransaction $new): int
    {
        $total = $new->total();

        if ($total > self::MAX_TOTAL_MILLIMES) {
            throw ValidationException::withMessages(['lines' => ['lines.total_too_high']]);
        }

        return $total;
    }

    private function insertTransaction(NewTransaction $new, int $total): bool
    {
        $lineCount = count($new->lines);

        try {
            Transaction::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'kind' => $new->kind,
                'contact_id' => $new->contactId,
                'transaction_date' => $new->transactionDate,
                'reference' => $new->reference,
                'total' => $total,
                'line_count' => $lineCount,
                'created_by' => $new->recordedBy,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            $transactionQuery = Transaction::query();
            $winner = RecordWithSameId::find($transactionQuery, $new->id, $new->shopId, readLatest: true);

            if ($winner !== null) {
                return false;
            }

            throw $lostRace;
        }

        return true;
    }

    private function insertLines(NewTransaction $new): void
    {
        $recordedAt = Carbon::now();
        $transactionBytes = BinaryUuid::toBytes($new->id);
        $rows = [];

        foreach ($new->lines as $index => $line) {
            $lineBytes = BinaryUuid::toBytes($line->id);
            $productBytes = BinaryUuid::toBytes($line->productId);
            $amount = $line->amount();
            $rows[] = [
                'id' => $lineBytes,
                'transaction_id' => $transactionBytes,
                'position' => $index + 1,
                'shop_product_id' => $productBytes,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'amount' => $amount,
                'created_at' => $recordedAt,
            ];
        }

        TransactionLine::query()->insert($rows);
    }

    private function recordStock(NewTransaction $new): void
    {
        $movements = array_map(
            fn (NewTransactionLine $line): NewStockMovement => new NewStockMovement($line->productId, $line->quantity, $line->id),
            $new->lines,
        );

        $this->recordStockMovements->handle($new->shopId, StockMovementKind::Purchase, $movements);
    }
}
