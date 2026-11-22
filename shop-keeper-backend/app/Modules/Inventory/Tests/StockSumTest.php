<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\LossReason;
use App\Modules\Inventory\Tests\Support\InventoryScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

const STOCK_RECORD_TABLES = ['stock_adjustments', 'stock_adjustment_lines', 'stock_movements'];

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
});

it('keeps every product stock equal to its movements after any series of stock changes', function () {
    mt_srand(10);
    $products = InventoryScenario::products($this->shop, 4);
    $expectedStock = array_fill(0, 4, 0);
    $openingLines = [];

    foreach ($products as $index => $product) {
        $quantity = mt_rand(1, 30);
        $openingLines[] = InventoryScenario::openingLine($product, $quantity);
        $expectedStock[$index] = $quantity;
    }

    $opening = InventoryScenario::adjustment($openingLines);
    InventoryScenario::openStock($this, $opening)->assertCreated();
    $reasons = LossReason::cases();

    for ($round = 0; $round < 12; $round++) {
        $index = mt_rand(0, 3);
        $product = $products[$index];
        $change = mt_rand(0, 2);

        if ($change === 0) {
            $quantity = mt_rand(1, 20);
            $line = TransactionsScenario::line($product, $quantity, 100);
            $purchase = TransactionsScenario::purchase([$line]);
            TransactionsScenario::record($this, $purchase)->assertCreated();
            $isCancelled = mt_rand(0, 2) === 0;

            if ($isCancelled) {
                PaymentsScenario::cancel($this, 'transactions/'.$purchase['id'])->assertCreated();

                continue;
            }

            $expectedStock[$index] += $quantity;

            continue;
        }

        if ($change === 1) {
            $countedQuantity = mt_rand(0, 40);
            $line = InventoryScenario::countLine($product, $countedQuantity, $expectedStock[$index]);
            $count = InventoryScenario::adjustment([$line]);
            InventoryScenario::count($this, $count)->assertCreated();
            $expectedStock[$index] = $countedQuantity;

            continue;
        }

        $quantity = mt_rand(1, 15);
        $reasonIndex = array_rand($reasons);
        $line = InventoryScenario::lossLine($product, $quantity, $reasons[$reasonIndex]->value);
        $loss = InventoryScenario::adjustment([$line]);
        InventoryScenario::lose($this, $loss)->assertCreated();
        $expectedStock[$index] -= $quantity;
    }

    foreach ($products as $index => $product) {
        $stock = InventoryScenario::stockOf($product);
        $movementSum = InventoryScenario::sumOfMovements($product);
        expect([$stock, $movementSum])->toBe([$expectedStock[$index], $expectedStock[$index]]);
    }
});

it('never updates or deletes an adjustment, a line or a stock movement', function () {
    $writes = [];
    DB::listen(function (QueryExecuted $query) use (&$writes): void {
        $sql = strtolower($query->sql);
        $isChange = preg_match('/^\s*(update|delete)\b/', $sql) === 1;

        if ($isChange) {
            $writes[] = $sql;
        }
    });
    $product = InventoryScenario::product($this->shop);
    $openingLine = InventoryScenario::openingLine($product, 10);
    $opening = InventoryScenario::adjustment([$openingLine]);
    $secondOpeningLine = InventoryScenario::openingLine($product, 5);
    $secondOpening = InventoryScenario::adjustment([$secondOpeningLine]);
    $countLine = InventoryScenario::countLine($product, 8, 10);
    $count = InventoryScenario::adjustment([$countLine]);
    $lossLine = InventoryScenario::lossLine($product, 2);
    $loss = InventoryScenario::adjustment([$lossLine]);
    $productUri = 'products/'.$product->uuid();

    InventoryScenario::openStock($this, $opening)->assertCreated();
    InventoryScenario::openStock($this, $opening)->assertOk();
    InventoryScenario::openStock($this, $secondOpening)->assertStatus(422);
    InventoryScenario::count($this, $count)->assertCreated();
    InventoryScenario::count($this, $count)->assertOk();
    InventoryScenario::lose($this, $loss)->assertCreated();
    InventoryScenario::lose($this, $loss)->assertOk();
    InventoryScenario::history($this, $product)->assertOk();
    ContactsScenario::send($this, 'PATCH', $productUri, ['low_stock_level' => 3])->assertOk();

    $tablesPattern = implode('|', STOCK_RECORD_TABLES);
    $stockRecordWrites = preg_grep('/^\s*(update|delete\s+from)\s+`?('.$tablesPattern.')`?\s/', $writes);
    expect($writes)->not->toBe([]);
    expect($stockRecordWrites)->toBe([]);
});
