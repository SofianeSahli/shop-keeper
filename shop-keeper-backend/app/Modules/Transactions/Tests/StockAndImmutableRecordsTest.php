<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

const IMMUTABLE_TABLES = ['transactions', 'transaction_lines', 'transaction_cancellations', 'payments', 'payment_cancellations', 'stock_movements'];

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
});

it('keeps every product stock equal to its movements after any series of purchases and cancellations', function () {
    mt_srand(21);
    $products = TransactionsScenario::products($this->shop, 4);
    $expectedStock = array_fill(0, 4, 0);

    for ($round = 0; $round < 10; $round++) {
        $lineCount = mt_rand(1, 4);
        $chosenIndexes = array_rand($products, $lineCount);
        $chosenIndexes = (array) $chosenIndexes;
        $lines = [];
        $quantities = [];

        foreach ($chosenIndexes as $index) {
            $quantity = mt_rand(1, 50);
            $lines[] = TransactionsScenario::line($products[$index], $quantity, 100);
            $quantities[$index] = $quantity;
        }

        $purchase = TransactionsScenario::purchase($lines);
        TransactionsScenario::record($this, $purchase)->assertCreated();
        $isCancelled = mt_rand(0, 2) === 0;

        if ($isCancelled) {
            PaymentsScenario::cancel($this, 'transactions/'.$purchase['id'])->assertCreated();

            continue;
        }

        foreach ($quantities as $index => $quantity) {
            $expectedStock[$index] += $quantity;
        }
    }

    foreach ($products as $index => $product) {
        $stock = TransactionsScenario::stockOf($product);
        $movementSum = TransactionsScenario::movementSumOf($product);
        expect([$stock, $movementSum])->toBe([$expectedStock[$index], $expectedStock[$index]]);
    }
});

it('never updates or deletes a transaction, line, payment, cancellation or stock movement', function () {
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        $sql = strtolower($query->sql);
        $isChange = preg_match('/^\s*(update|delete)\b/', $sql) === 1;

        if ($isChange) {
            $writes[] = $sql;
        }
    });
    $supplier = ContactsScenario::contact($this->shop);
    $supplierId = $supplier->uuid();
    $product = TransactionsScenario::product($this->shop);
    $line = TransactionsScenario::line($product, 5, 2000);
    $purchase = TransactionsScenario::purchase([$line], ['contact_id' => $supplierId]);
    $payment = PaymentsScenario::payment(['transaction_id' => $purchase['id'], 'amount' => 4000]);
    $tooMuch = PaymentsScenario::payment(['transaction_id' => $purchase['id'], 'amount' => 99000]);
    $onAccount = PaymentsScenario::payment(['contact_id' => $supplierId, 'direction' => 'received']);
    $purchaseCancellation = PaymentsScenario::cancellation();

    TransactionsScenario::record($this, $purchase)->assertCreated();
    TransactionsScenario::record($this, $purchase)->assertOk();
    PaymentsScenario::record($this, $payment)->assertCreated();
    PaymentsScenario::record($this, $payment)->assertOk();
    PaymentsScenario::record($this, $tooMuch)->assertStatus(422);
    PaymentsScenario::record($this, $onAccount)->assertCreated();
    ContactsScenario::send($this, 'POST', 'transactions/'.$purchase['id'].'/cancellation', $purchaseCancellation)->assertStatus(409);
    PaymentsScenario::cancel($this, 'payments/'.$payment['id'])->assertCreated();
    PaymentsScenario::cancel($this, 'payments/'.$onAccount['id'])->assertCreated();
    ContactsScenario::send($this, 'POST', 'transactions/'.$purchase['id'].'/cancellation', $purchaseCancellation)->assertCreated();
    ContactsScenario::send($this, 'POST', 'transactions/'.$purchase['id'].'/cancellation', $purchaseCancellation)->assertOk();
    ContactsScenario::send($this, 'PATCH', 'contacts/'.$supplierId, ['name' => 'Renamed'])->assertOk();
    ContactsScenario::send($this, 'GET', 'transactions')->assertOk();
    ContactsScenario::send($this, 'GET', 'payments')->assertOk();

    $tablesPattern = implode('|', IMMUTABLE_TABLES);
    $immutableWrites = preg_grep('/^\s*(update|delete\s+from)\s+`?('.$tablesPattern.')`?\s/', $writes);
    expect($writes)->not->toBe([]);
    expect($immutableWrites)->toBe([]);
});
