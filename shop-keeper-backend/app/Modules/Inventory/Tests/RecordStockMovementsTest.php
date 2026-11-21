<?php

use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Inventory\Actions\RecordStockMovements;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Support\NewStockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

beforeEach(function () {
    ['shop' => $this->shop] = CatalogScenario::merchantWithShop();
    $this->tomatoes = CatalogScenario::shopProduct($this->shop);
    $this->tomatoesId = $this->tomatoes->uuid();
    $this->flour = CatalogScenario::shopProduct($this->shop);
    $this->flourId = $this->flour->uuid();
    $this->recordStockMovements = app(RecordStockMovements::class);
});

it('stores movements and changes each product stock by their sum', function () {
    $firstSource = (string) Str::uuid7();
    $secondSource = (string) Str::uuid7();
    $thirdSource = (string) Str::uuid7();
    $movements = [
        new NewStockMovement($this->tomatoesId, 10, $firstSource),
        new NewStockMovement($this->flourId, 4, $secondSource),
    ];
    $cancellations = [new NewStockMovement($this->tomatoesId, -3, $thirdSource)];

    $this->recordStockMovements->handle($this->shop->id, StockMovementKind::Purchase, $movements);
    $this->recordStockMovements->handle($this->shop->id, StockMovementKind::PurchaseCancellation, $cancellations);

    $tomatoStock = $this->tomatoes->fresh()->stock_quantity;
    $flourStock = $this->flour->fresh()->stock_quantity;
    expect([$tomatoStock, $flourStock])->toBe([7, 4]);

    $stored = StockMovement::query()->orderBy('quantity')->get();
    $storedRows = $stored->map(fn (StockMovement $movement): array => [$movement->kind, $movement->quantity, $movement->source_id, $movement->shop_product_id])->all();
    expect($storedRows)->toBe([
        [StockMovementKind::PurchaseCancellation, -3, $thirdSource, $this->tomatoesId],
        [StockMovementKind::Purchase, 4, $secondSource, $this->flourId],
        [StockMovementKind::Purchase, 10, $firstSource, $this->tomatoesId],
    ]);
});

it('refuses a second movement of the same kind for the same source', function () {
    $source = (string) Str::uuid7();
    $movements = [new NewStockMovement($this->tomatoesId, 5, $source)];
    $this->recordStockMovements->handle($this->shop->id, StockMovementKind::Purchase, $movements);

    $repeat = fn () => $this->recordStockMovements->handle($this->shop->id, StockMovementKind::Purchase, $movements);

    expect($repeat)->toThrow(UniqueConstraintViolationException::class);

    $tomatoStock = $this->tomatoes->fresh()->stock_quantity;
    expect($tomatoStock)->toBe(5);
});
