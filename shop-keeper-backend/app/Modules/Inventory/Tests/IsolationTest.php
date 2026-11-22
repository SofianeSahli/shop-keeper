<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Tests\Support\InventoryScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
    ['shop' => $this->otherShop, 'token' => $this->otherToken] = InventoryScenario::merchantWithShop();
    $this->tomatoes = InventoryScenario::product($this->shop);
    $this->foreignProduct = InventoryScenario::product($this->otherShop, ['stock_quantity' => 0, 'low_stock_level' => 10]);
});

it('refuses a product of another shop in every adjustment and records nothing', function (string $kind) {
    $ownLine = match ($kind) {
        'opening' => InventoryScenario::openingLine($this->tomatoes),
        'count' => InventoryScenario::countLine($this->tomatoes, 3, 0),
        'loss' => InventoryScenario::lossLine($this->tomatoes),
    };
    $foreignLine = [...$ownLine, 'id' => InventoryScenario::newId(), 'product_id' => $this->foreignProduct->uuid()];
    $body = InventoryScenario::adjustment([$ownLine, $foreignLine]);

    $response = match ($kind) {
        'opening' => InventoryScenario::openStock($this, $body),
        'count' => InventoryScenario::count($this, $body),
        'loss' => InventoryScenario::lose($this, $body),
    };

    ContactsScenario::assertFieldError($response, 'lines.1.product_id', 'product_id.unknown');
    $adjustmentCount = StockAdjustment::query()->count();
    $movementCount = StockMovement::query()->count();
    $foreignStock = InventoryScenario::stockOf($this->foreignProduct);
    expect([$adjustmentCount, $movementCount, $foreignStock])->toBe([0, 0, 0]);
})->with(['opening', 'count', 'loss']);

it('hides the history and the level of a product of another shop', function () {
    $foreignUri = 'products/'.$this->foreignProduct->uuid();

    InventoryScenario::history($this, $this->foreignProduct)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'product.not_found']]);
    ContactsScenario::send($this, 'PATCH', $foreignUri, ['low_stock_level' => 1])
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'product.not_found']]);

    $foreignLevel = $this->foreignProduct->fresh()->low_stock_level;
    expect($foreignLevel)->toBe(10);
});

it('never lists a product of another shop as low on stock', function () {
    ContactsScenario::send($this, 'GET', 'products/low-stock')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('refuses the adjustment and line ids of another shop without revealing them', function () {
    $foreignLine = InventoryScenario::openingLine($this->foreignProduct, 4);
    $foreign = InventoryScenario::adjustment([$foreignLine]);
    InventoryScenario::openStock($this, $foreign, $this->otherToken)->assertCreated();
    $ownLine = InventoryScenario::openingLine($this->tomatoes);
    $sameAdjustmentId = InventoryScenario::adjustment([$ownLine], ['id' => $foreign['id']]);
    $lineWithTakenId = [...InventoryScenario::openingLine($this->tomatoes), 'id' => $foreignLine['id']];
    $sameLineId = InventoryScenario::adjustment([$lineWithTakenId]);

    $adjustmentRefusal = InventoryScenario::openStock($this, $sameAdjustmentId);
    $lineRefusal = InventoryScenario::openStock($this, $sameLineId);

    ContactsScenario::assertFieldError($adjustmentRefusal, 'id', 'id.taken');
    ContactsScenario::assertFieldError($lineRefusal, 'lines.0.id', 'id.taken');
    $adjustmentRefusal->assertJsonMissingPath('data');
    $lineRefusal->assertJsonMissingPath('data');
    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(0);
});
