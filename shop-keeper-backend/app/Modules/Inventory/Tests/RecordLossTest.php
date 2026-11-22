<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\LossReason;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Tests\Support\InventoryScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
    $this->tomatoes = InventoryScenario::product($this->shop);
    $this->flour = InventoryScenario::product($this->shop);
    $openingLines = [
        InventoryScenario::openingLine($this->tomatoes, 10),
        InventoryScenario::openingLine($this->flour, 10),
    ];
    $opening = InventoryScenario::adjustment($openingLines);
    InventoryScenario::openStock($this, $opening)->assertCreated();
});

it('takes the lost units out of the stock with their reason and trimmed note', function () {
    $tomatoLine = InventoryScenario::lossLine($this->tomatoes, 2, 'broken', '  fell from the shelf  ');
    $flourLine = InventoryScenario::lossLine($this->flour, 1, 'expired');
    $body = InventoryScenario::adjustment([$tomatoLine, $flourLine]);

    InventoryScenario::lose($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.kind', 'loss')
        ->assertJsonPath('data.lines', [
            ['id' => $tomatoLine['id'], 'product_id' => $tomatoLine['product_id'], 'quantity' => -2, 'counted_quantity' => null, 'shown_quantity' => null, 'reason' => 'broken', 'note' => 'fell from the shelf'],
            ['id' => $flourLine['id'], 'product_id' => $flourLine['product_id'], 'quantity' => -1, 'counted_quantity' => null, 'shown_quantity' => null, 'reason' => 'expired', 'note' => null],
        ]);

    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    $flourStock = InventoryScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([8, 9]);
});

it('accepts a loss larger than the stock', function () {
    $line = InventoryScenario::lossLine($this->tomatoes, 15, 'stolen');
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::lose($this, $body)->assertCreated();

    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(-5);
});

it('accepts every loss reason', function (LossReason $reason) {
    $line = InventoryScenario::lossLine($this->tomatoes, 1, $reason->value);
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::lose($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.lines.0.reason', $reason->value);
})->with(LossReason::cases());

it('answers the existing loss when the same id is sent again', function () {
    $line = InventoryScenario::lossLine($this->tomatoes, 2);
    $body = InventoryScenario::adjustment([$line]);
    InventoryScenario::lose($this, $body)->assertCreated();

    InventoryScenario::lose($this, $body)
        ->assertOk()
        ->assertJsonPath('data.id', $body['id']);

    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(8);
});

it('answers a stable code for each wrong loss field and records nothing', function (Closure $change, string $field, string $code) {
    $tomatoLine = InventoryScenario::lossLine($this->tomatoes, 2);
    $flourLine = InventoryScenario::lossLine($this->flour, 1);
    $valid = InventoryScenario::adjustment([$tomatoLine, $flourLine]);
    $body = $change($valid);

    $response = InventoryScenario::lose($this, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
    $adjustmentCount = StockAdjustment::query()->count();
    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    expect([$adjustmentCount, $tomatoStock])->toBe([1, 10]);
})->with([
    'no reason' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['reason' => null]), 'lines.1.reason', 'reason.required'],
    'unknown reason' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['reason' => 'lost']), 'lines.1.reason', 'reason.invalid'],
    'note not text' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['note' => 12]), 'lines.1.note', 'note.invalid'],
    'note too long' => [function (array $body): array {
        $note = str_repeat('n', 256);

        return InventoryScenario::lineChanged($body, 1, ['note' => $note]);
    }, 'lines.1.note', 'note.too_long'],
    'no quantity' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => null]), 'lines.1.quantity', 'quantity.required'],
    'quantity not whole' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 1.5]), 'lines.1.quantity', 'quantity.integer'],
    'quantity of 0' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 0]), 'lines.1.quantity', 'quantity.too_low'],
    'quantity too high' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 1_000_000]), 'lines.1.quantity', 'quantity.too_high'],
]);
