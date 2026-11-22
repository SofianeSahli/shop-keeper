<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Inventory\Tests\Support\InventoryScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use App\Shared\Database\BinaryUuid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->tomatoes = InventoryScenario::product($this->shop);
    $this->flour = InventoryScenario::product($this->shop);
});

it('sets the opening stock of several products at once', function () {
    $tomatoLine = InventoryScenario::openingLine($this->tomatoes, 24);
    $flourLine = InventoryScenario::openingLine($this->flour, 10);
    $body = InventoryScenario::adjustment([$tomatoLine, $flourLine], ['adjustment_date' => '2026-11-20']);

    InventoryScenario::openStock($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.kind', 'opening_stock')
        ->assertJsonPath('data.adjustment_date', '2026-11-20')
        ->assertJsonPath('data.line_count', 2)
        ->assertJsonPath('data.recorded_by', $this->account)
        ->assertJsonPath('data.lines', [
            ['id' => $tomatoLine['id'], 'product_id' => $tomatoLine['product_id'], 'quantity' => 24, 'counted_quantity' => null, 'shown_quantity' => null, 'reason' => null, 'note' => null],
            ['id' => $flourLine['id'], 'product_id' => $flourLine['product_id'], 'quantity' => 10, 'counted_quantity' => null, 'shown_quantity' => null, 'reason' => null, 'note' => null],
        ]);

    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    $flourStock = InventoryScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([24, 10]);

    $movements = StockMovement::query()->orderByDesc('quantity')->get();
    $movementRows = $movements->map(fn (StockMovement $movement): array => [$movement->kind, $movement->quantity, $movement->source_id])->all();
    expect($movementRows)->toBe([
        [StockMovementKind::OpeningStock, 24, $tomatoLine['id']],
        [StockMovementKind::OpeningStock, 10, $flourLine['id']],
    ]);
});

it('dates the opening stock today in the shop time zone when no date is sent', function () {
    $lateEvening = CarbonImmutable::parse('2026-11-21 23:30:00', 'UTC');
    $this->travelTo($lateEvening);
    $line = InventoryScenario::openingLine($this->tomatoes);
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::openStock($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.adjustment_date', '2026-11-22');
});

it('keeps a past date and refuses a future one', function () {
    $noon = CarbonImmutable::parse('2026-11-21 12:00:00', 'Africa/Tunis');
    $this->travelTo($noon);
    $pastLine = InventoryScenario::openingLine($this->tomatoes);
    $past = InventoryScenario::adjustment([$pastLine], ['adjustment_date' => '2025-01-31']);
    $futureLine = InventoryScenario::openingLine($this->flour);
    $future = InventoryScenario::adjustment([$futureLine], ['adjustment_date' => '2026-11-22']);

    InventoryScenario::openStock($this, $past)->assertCreated()->assertJsonPath('data.adjustment_date', '2025-01-31');
    $refused = InventoryScenario::openStock($this, $future);

    ContactsScenario::assertFieldError($refused, 'adjustment_date', 'adjustment_date.in_future');
});

it('adds the opening stock to what purchases already brought', function () {
    $purchaseLine = TransactionsScenario::line($this->tomatoes, 5);
    $purchase = TransactionsScenario::purchase([$purchaseLine]);
    TransactionsScenario::record($this, $purchase)->assertCreated();
    $line = InventoryScenario::openingLine($this->tomatoes, 20);
    $body = InventoryScenario::adjustment([$line]);

    InventoryScenario::openStock($this, $body)->assertCreated();

    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(25);
});

it('refuses a second opening stock for a product and records nothing', function () {
    $firstLine = InventoryScenario::openingLine($this->tomatoes, 24);
    $first = InventoryScenario::adjustment([$firstLine]);
    InventoryScenario::openStock($this, $first)->assertCreated();
    $flourLine = InventoryScenario::openingLine($this->flour, 10);
    $againLine = InventoryScenario::openingLine($this->tomatoes, 30);
    $second = InventoryScenario::adjustment([$flourLine, $againLine]);

    $response = InventoryScenario::openStock($this, $second);

    ContactsScenario::assertFieldError($response, 'lines.1.product_id', 'product_id.opening_already_set');
    $adjustmentCount = StockAdjustment::query()->count();
    $tomatoStock = InventoryScenario::stockOf($this->tomatoes);
    $flourStock = InventoryScenario::stockOf($this->flour);
    expect([$adjustmentCount, $tomatoStock, $flourStock])->toBe([1, 24, 0]);
});

it('answers the existing opening stock when the same id is sent again, and changes stock once', function () {
    $line = InventoryScenario::openingLine($this->tomatoes, 24);
    $body = InventoryScenario::adjustment([$line]);
    InventoryScenario::openStock($this, $body)->assertCreated();

    InventoryScenario::openStock($this, $body)
        ->assertOk()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.lines.0.quantity', 24);

    $stock = InventoryScenario::stockOf($this->tomatoes);
    $adjustmentCount = StockAdjustment::query()->count();
    $movementCount = StockMovement::query()->count();
    expect([$stock, $adjustmentCount, $movementCount])->toBe([24, 1, 1]);
});

it('accepts only one of two different opening stocks racing on the same product', function () {
    $winnerLine = InventoryScenario::openingLine($this->tomatoes, 24);
    $winner = InventoryScenario::adjustment([$winnerLine]);
    $loserLine = InventoryScenario::openingLine($this->tomatoes, 30);
    $loser = InventoryScenario::adjustment([$loserLine]);
    $shopId = $this->shop->id;
    $account = $this->account;
    StockAdjustment::creating(function () use ($winner, $winnerLine, $shopId, $account): void {
        $winnerBytes = BinaryUuid::toBytes($winner['id']);
        $winnerExists = DB::table('stock_adjustments')->where('id', $winnerBytes)->exists();

        if ($winnerExists) {
            return;
        }

        $createdAt = now();
        $productBytes = BinaryUuid::toBytes($winnerLine['product_id']);
        $lineBytes = BinaryUuid::toBytes($winnerLine['id']);
        DB::table('stock_adjustments')->insert([
            'id' => $winnerBytes,
            'shop_id' => $shopId,
            'kind' => 'opening_stock',
            'adjustment_date' => '2026-11-21',
            'line_count' => 1,
            'created_by' => $account,
            'created_at' => $createdAt,
        ]);
        DB::table('stock_adjustment_lines')->insert([
            'id' => $lineBytes,
            'stock_adjustment_id' => $winnerBytes,
            'position' => 1,
            'shop_product_id' => $productBytes,
            'quantity' => 24,
            'opening_product_id' => $productBytes,
            'created_at' => $createdAt,
        ]);
    });

    $response = InventoryScenario::openStock($this, $loser);

    ContactsScenario::assertFieldError($response, 'lines.0.product_id', 'product_id.opening_already_set');
    $loserLineQuery = StockAdjustmentLine::query();
    $loserLineExists = $loserLineQuery->whereUuid($loserLine['id'])->exists();
    $movementCount = StockMovement::query()->count();
    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect([$loserLineExists, $movementCount, $stock])->toBe([false, 0, 0]);
});

it('records an opening stock of 200 lines and refuses 201', function () {
    $products = InventoryScenario::products($this->shop, 201);
    $lines = array_map(fn ($product): array => InventoryScenario::openingLine($product, 1), $products);
    $twoHundredLines = array_slice($lines, 0, 200);
    $accepted = InventoryScenario::adjustment($twoHundredLines);
    $tooMany = InventoryScenario::adjustment($lines);

    $refusal = InventoryScenario::openStock($this, $tooMany);
    InventoryScenario::openStock($this, $accepted)
        ->assertCreated()
        ->assertJsonPath('data.line_count', 200);

    ContactsScenario::assertFieldError($refusal, 'lines', 'lines.too_many');
});

it('answers a stable code for each wrong field', function (Closure $change, string $field, string $code) {
    $tomatoLine = InventoryScenario::openingLine($this->tomatoes);
    $flourLine = InventoryScenario::openingLine($this->flour);
    $valid = InventoryScenario::adjustment([$tomatoLine, $flourLine]);
    $body = $change($valid);

    $response = InventoryScenario::openStock($this, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
    $adjustmentCount = StockAdjustment::query()->count();
    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect([$adjustmentCount, $stock])->toBe([0, 0]);
})->with([
    'no id' => [fn (array $body): array => [...$body, 'id' => null], 'id', 'id.required'],
    'id not a uuid' => [fn (array $body): array => [...$body, 'id' => 'abc'], 'id', 'id.invalid'],
    'date not a date' => [fn (array $body): array => [...$body, 'adjustment_date' => '21/11/2026'], 'adjustment_date', 'adjustment_date.invalid'],
    'no lines' => [fn (array $body): array => [...$body, 'lines' => []], 'lines', 'lines.required'],
    'lines not a list' => [fn (array $body): array => [...$body, 'lines' => 'many'], 'lines', 'lines.required'],
    'line not an object' => [fn (array $body): array => [...$body, 'lines' => [$body['lines'][0], 'line']], 'lines.1', 'line.invalid'],
    'line without id' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['id' => null]), 'lines.1.id', 'id.required'],
    'line id not a uuid' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['id' => 'x']), 'lines.1.id', 'id.invalid'],
    'line id twice' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['id' => $body['lines'][0]['id']]), 'lines.1.id', 'id.duplicate'],
    'line without product' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['product_id' => null]), 'lines.1.product_id', 'product_id.required'],
    'product not a uuid' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['product_id' => 'x']), 'lines.1.product_id', 'product_id.invalid'],
    'product twice' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['product_id' => $body['lines'][0]['product_id']]), 'lines.1.product_id', 'product_id.duplicate'],
    'unknown product' => [function (array $body): array {
        $unknownId = InventoryScenario::newId();

        return InventoryScenario::lineChanged($body, 1, ['product_id' => $unknownId]);
    }, 'lines.1.product_id', 'product_id.unknown'],
    'no quantity' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => null]), 'lines.1.quantity', 'quantity.required'],
    'quantity not whole' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 1.5]), 'lines.1.quantity', 'quantity.integer'],
    'quantity as text' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => '3']), 'lines.1.quantity', 'quantity.integer'],
    'quantity of 0' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 0]), 'lines.1.quantity', 'quantity.too_low'],
    'quantity too high' => [fn (array $body): array => InventoryScenario::lineChanged($body, 1, ['quantity' => 1_000_000]), 'lines.1.quantity', 'quantity.too_high'],
]);

it('refuses recording without a session or without a shop', function (string $uri) {
    $line = InventoryScenario::openingLine($this->tomatoes);
    $body = InventoryScenario::adjustment([$line]);
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'POST', $uri, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'POST', $uri, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
})->with(['stock/openings', 'stock/counts', 'stock/losses']);
