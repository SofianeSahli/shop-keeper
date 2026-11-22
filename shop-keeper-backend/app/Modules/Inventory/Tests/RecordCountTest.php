<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Tests\Support\InventoryScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
    $this->tomatoes = InventoryScenario::product($this->shop);
    $this->flour = InventoryScenario::product($this->shop);
    $openingLines = [
        InventoryScenario::openingLine($this->tomatoes, 20),
        InventoryScenario::openingLine($this->flour, 10),
    ];
    $opening = InventoryScenario::adjustment($openingLines);
    InventoryScenario::openStock($this, $opening)->assertCreated();
    $this->opening = $opening;
});

it('records the counted minus the shown stock for several products', function () {
    $tomatoLine = InventoryScenario::countLine($this->tomatoes, 17, 20);
    $flourLine = InventoryScenario::countLine($this->flour, 12, 10);
    $body = InventoryScenario::adjustment([$tomatoLine, $flourLine]);

    InventoryScenario::count($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.kind', 'count')
        ->assertJsonPath('data.lines', [
            ['id' => $tomatoLine['id'], 'product_id' => $tomatoLine['product_id'], 'quantity' => -3, 'counted_quantity' => 17, 'shown_quantity' => 20, 'reason' => null, 'note' => null],
            ['id' => $flourLine['id'], 'product_id' => $flourLine['product_id'], 'quantity' => 2, 'counted_quantity' => 12, 'shown_quantity' => 10, 'reason' => null, 'note' => null],
        ]);

    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    $flourStock = InventoryScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([17, 12]);
});

it('records a difference of 0 and keeps the stock', function () {
    $line = InventoryScenario::countLine($this->tomatoes, 20, 20);
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::count($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.lines.0.quantity', 0);

    $stock = InventoryScenario::stockOf($this->tomatoes);
    $movementCount = StockMovement::query()->count();
    expect([$stock, $movementCount])->toBe([20, 3]);
});

it('keeps a purchase recorded between the shown stock and the count', function () {
    $purchaseLine = TransactionsScenario::line($this->tomatoes, 5);
    $purchase = TransactionsScenario::purchase([$purchaseLine]);
    TransactionsScenario::record($this, $purchase)->assertCreated();
    $line = InventoryScenario::countLine($this->tomatoes, 17, 20);
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::count($this, $body)->assertCreated();

    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(22);
});

it('accepts a shown stock below zero and a count of 0', function () {
    $tomatoLine = InventoryScenario::countLine($this->tomatoes, 0, 20);
    $flourLine = InventoryScenario::countLine($this->flour, 4, -6);
    $body = InventoryScenario::adjustment([$tomatoLine, $flourLine]);

    InventoryScenario::count($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.lines.0.quantity', -20)
        ->assertJsonPath('data.lines.1.quantity', 10);

    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    $flourStock = InventoryScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([0, 20]);
});

it('answers the existing count when the same id is sent again', function () {
    $line = InventoryScenario::countLine($this->tomatoes, 17, 20);
    $body = InventoryScenario::adjustment([$line]);
    InventoryScenario::count($this, $body)->assertCreated();

    InventoryScenario::count($this, $body)
        ->assertOk()
        ->assertJsonPath('data.id', $body['id']);

    $stock = InventoryScenario::stockOf($this->tomatoes);
    $adjustmentCount = StockAdjustment::query()->count();
    expect([$stock, $adjustmentCount])->toBe([17, 2]);
});

it('refuses the id of an opening stock reused for a count', function () {
    $line = InventoryScenario::countLine($this->tomatoes, 17, 20);
    $body = InventoryScenario::adjustment([$line], ['id' => $this->opening['id']]);

    $response = InventoryScenario::count($this, $body);

    ContactsScenario::assertFieldError($response, 'id', 'id.taken');
    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(20);
});

it('answers a stable code for each wrong count field', function (Closure $change, string $field, string $code) {
    $tomatoLine = InventoryScenario::countLine($this->tomatoes, 17, 20);
    $flourLine = InventoryScenario::countLine($this->flour, 12, 10);
    $valid = InventoryScenario::adjustment([$tomatoLine, $flourLine]);
    $body = $change($valid);

    $response = InventoryScenario::count($this, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
    $adjustmentCount = StockAdjustment::query()->count();
    expect($adjustmentCount)->toBe(1);
})->with([
    'no counted quantity' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['counted_quantity' => null]), 'lines.1.counted_quantity', 'counted_quantity.required'],
    'counted quantity not whole' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['counted_quantity' => 1.5]), 'lines.1.counted_quantity', 'counted_quantity.integer'],
    'counted quantity as text' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['counted_quantity' => '3']), 'lines.1.counted_quantity', 'counted_quantity.integer'],
    'negative counted quantity' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['counted_quantity' => -1]), 'lines.1.counted_quantity', 'counted_quantity.negative'],
    'counted quantity too high' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['counted_quantity' => 1_000_000]), 'lines.1.counted_quantity', 'counted_quantity.too_high'],
    'no shown quantity' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['shown_quantity' => null]), 'lines.1.shown_quantity', 'shown_quantity.required'],
    'shown quantity not whole' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['shown_quantity' => 1.5]), 'lines.1.shown_quantity', 'shown_quantity.integer'],
    'shown quantity too low' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['shown_quantity' => -1_000_000_000]), 'lines.1.shown_quantity', 'shown_quantity.too_low'],
    'shown quantity too high' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['shown_quantity' => 1_000_000_000]), 'lines.1.shown_quantity', 'shown_quantity.too_high'],
    'unknown product' => [function (array $body): array {
        $unknownId = InventoryScenario::newId();

        return InventoryScenario::lineChanged($body, 1, ['product_id' => $unknownId]);
    }, 'lines.1.product_id', 'product_id.unknown'],
]);
