<?php

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchasing\Models\Delivery;
use App\Modules\Purchasing\Models\DeliveryLine;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
    $this->supplier = Scenario::supplier($this->shop, ['name' => 'Grossiste Ben Ali']);
    $this->tomatoes = Scenario::product($this->shop, ['name' => 'Tomates', 'sale_price' => 1800, 'purchase_price' => 1100]);
    $this->harissa = Scenario::product($this->shop, ['name' => 'Harissa', 'sale_price' => 3000, 'purchase_price' => null]);
});

function recordDelivery(object $test, array $body, ?string $token = null)
{
    return Scenario::send($test, 'POST', 'deliveries', $body, $token);
}

function nothingRecorded(): void
{
    $deliveryCount = Delivery::query()->count();
    $lineCount = DeliveryLine::query()->count();
    $movementCount = StockMovement::query()->count();
    $paymentCount = SupplierPayment::query()->count();

    expect([$deliveryCount, $lineCount, $movementCount, $paymentCount])->toBe([0, 0, 0, 0]);
}

it('records a delivery, brings stock in and leaves prices alone', function () {
    $tomatoLine = Scenario::line($this->tomatoes, quantity: 24, unitPrice: 1200);
    $harissaLine = Scenario::line($this->harissa, quantity: 10, unitPrice: 2520);
    $supplierId = $this->supplier->uuid();
    $body = Scenario::delivery([$tomatoLine, $harissaLine], ['supplier_id' => $supplierId, 'delivery_date' => '2026-11-18', 'reference' => 'BL-2041']);

    $response = recordDelivery($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.supplier', ['id' => $supplierId, 'name' => 'Grossiste Ben Ali'])
        ->assertJsonPath('data.delivery_date', '2026-11-18')
        ->assertJsonPath('data.reference', 'BL-2041')
        ->assertJsonPath('data.total', 54000)
        ->assertJsonPath('data.line_count', 2)
        ->assertJsonPath('data.cancelled_at', null)
        ->assertJsonPath('data.payment', null);

    $lines = $response->json('data.lines');
    expect($lines)->toBe([
        [...$tomatoLine, 'amount' => 28800],
        [...$harissaLine, 'amount' => 25200],
    ]);

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    $harissaStock = Scenario::stockOf($this->harissa);
    expect([$tomatoStock, $harissaStock])->toBe([24, 10]);

    $freshTomatoes = $this->tomatoes->fresh();
    $freshHarissa = $this->harissa->fresh();
    expect([$freshTomatoes->purchase_price, $freshTomatoes->sale_price, $freshHarissa->purchase_price])->toBe([1100, 1800, null]);

    $tomatoesId = $this->tomatoes->uuid();
    Scenario::send($this, 'GET', "products/{$tomatoesId}")->assertJsonPath('data.stock_quantity', 24);
});

it('accepts a delivery with no supplier', function () {
    $line = Scenario::line($this->tomatoes);
    $body = Scenario::delivery([$line]);

    recordDelivery($this, $body)->assertCreated()->assertJsonPath('data.supplier', null);
});

it('accepts 200 lines in one request', function () {
    $lines = [];

    for ($index = 0; $index < 200; $index++) {
        $product = Scenario::product($this->shop);
        $lines[] = Scenario::line($product, quantity: 1, unitPrice: 500);
    }

    $body = Scenario::delivery($lines);

    recordDelivery($this, $body)->assertCreated()->assertJsonPath('data.line_count', 200)->assertJsonPath('data.total', 100000);
});

it('dates a delivery today in the shop time zone by default', function () {
    $lateEveningInUtc = CarbonImmutable::parse('2026-11-19 23:30:00', 'UTC');
    $this->travelTo($lateEveningInUtc);
    $line = Scenario::line($this->tomatoes);
    $body = Scenario::delivery([$line]);

    recordDelivery($this, $body)->assertCreated()->assertJsonPath('data.delivery_date', '2026-11-20');
});

it('keeps a past date and refuses a future one', function () {
    $noon = CarbonImmutable::parse('2026-11-20 12:00:00', 'UTC');
    $this->travelTo($noon);
    $line = Scenario::line($this->tomatoes);
    $pastBody = Scenario::delivery([$line], ['delivery_date' => '2025-01-31']);
    $otherLine = Scenario::line($this->tomatoes);
    $futureBody = Scenario::delivery([$otherLine], ['delivery_date' => '2026-11-21']);

    recordDelivery($this, $pastBody)->assertCreated()->assertJsonPath('data.delivery_date', '2025-01-31');
    $response = recordDelivery($this, $futureBody);
    Scenario::assertFieldError($response, 'delivery_date', 'delivery_date.in_future');
});

it('records a payment made with the delivery', function () {
    $line = Scenario::line($this->tomatoes, quantity: 10, unitPrice: 5000);
    $supplierId = $this->supplier->uuid();
    $paymentId = Scenario::newId();
    $body = Scenario::delivery([$line], [
        'supplier_id' => $supplierId,
        'delivery_date' => '2026-11-18',
        'payment' => ['id' => $paymentId, 'amount' => 20000, 'method' => 'cash'],
    ]);

    recordDelivery($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.payment.id', $paymentId)
        ->assertJsonPath('data.payment.amount', 20000)
        ->assertJsonPath('data.payment.method', 'cash')
        ->assertJsonPath('data.payment.payment_date', '2026-11-18')
        ->assertJsonPath('data.payment.delivery_id', $body['id']);

    Scenario::send($this, 'GET', "suppliers/{$supplierId}")->assertJsonPath('data.balance', 30000);
});

it('refuses a payment on a delivery with no supplier', function () {
    $line = Scenario::line($this->tomatoes);
    $paymentId = Scenario::newId();
    $body = Scenario::delivery([$line], ['payment' => ['id' => $paymentId, 'amount' => 1000, 'method' => 'cash']]);

    $response = recordDelivery($this, $body);
    Scenario::assertFieldError($response, 'payment', 'payment.supplier_required');
    nothingRecorded();
});

it('records once when the same delivery is sent again', function () {
    $line = Scenario::line($this->tomatoes, quantity: 6);
    $supplierId = $this->supplier->uuid();
    $paymentId = Scenario::newId();
    $body = Scenario::delivery([$line], ['supplier_id' => $supplierId, 'payment' => ['id' => $paymentId, 'amount' => 1000, 'method' => 'card']]);

    recordDelivery($this, $body)->assertCreated();
    recordDelivery($this, $body)->assertOk()->assertJsonPath('data.id', $body['id']);

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    $deliveryCount = Delivery::query()->count();
    $paymentCount = SupplierPayment::query()->count();
    expect([$tomatoStock, $deliveryCount, $paymentCount])->toBe([6, 1, 1]);
});

it('accepts free goods at a unit price of 0', function () {
    $line = Scenario::line($this->tomatoes, quantity: 2, unitPrice: 0);
    $body = Scenario::delivery([$line]);

    recordDelivery($this, $body)->assertCreated()->assertJsonPath('data.total', 0);

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    expect($tomatoStock)->toBe(2);
});

it('names the wrong line and field and records nothing', function (Closure $secondLine, string $field, string $code) {
    $firstLine = Scenario::line($this->tomatoes);
    $wrongLine = $secondLine($this);
    $body = Scenario::delivery([$firstLine, $wrongLine]);

    $response = recordDelivery($this, $body);
    Scenario::assertFieldError($response, "lines.1.{$field}", $code);
    nothingRecorded();
})->with([
    'quantity 0' => [fn ($test) => Scenario::line($test->harissa, quantity: 0), 'quantity', 'quantity.too_low'],
    'quantity too high' => [fn ($test) => Scenario::line($test->harissa, quantity: 1_000_000), 'quantity', 'quantity.too_high'],
    'quantity not whole' => [fn ($test) => [...Scenario::line($test->harissa), 'quantity' => 1.5], 'quantity', 'quantity.integer'],
    'negative price' => [fn ($test) => Scenario::line($test->harissa, unitPrice: -1), 'unit_price', 'unit_price.negative'],
    'price too high' => [fn ($test) => Scenario::line($test->harissa, unitPrice: 1_000_000_000), 'unit_price', 'unit_price.too_high'],
    'no product' => [fn ($test) => [...Scenario::line($test->harissa), 'product_id' => null], 'product_id', 'product_id.required'],
    'unknown product' => [fn ($test) => [...Scenario::line($test->harissa), 'product_id' => Scenario::newId()], 'product_id', 'product_id.unknown'],
    'bad line id' => [fn ($test) => [...Scenario::line($test->harissa), 'id' => 'nope'], 'id', 'id.invalid'],
]);

it('refuses the same product twice and the same line id twice', function () {
    $firstLine = Scenario::line($this->tomatoes);
    $sameProduct = Scenario::line($this->tomatoes);
    $productBody = Scenario::delivery([$firstLine, $sameProduct]);
    $harissaLine = Scenario::line($this->harissa);
    $sameLineId = [...$harissaLine, 'id' => $firstLine['id']];
    $lineIdBody = Scenario::delivery([$firstLine, $sameLineId]);

    $response = recordDelivery($this, $productBody);
    Scenario::assertFieldError($response, 'lines.1.product_id', 'product_id.duplicate');
    $response = recordDelivery($this, $lineIdBody);
    Scenario::assertFieldError($response, 'lines.1.id', 'id.duplicate');
    nothingRecorded();
});

it('refuses a delivery with no line or too many lines', function () {
    $noLines = Scenario::delivery([]);
    $line = Scenario::line($this->tomatoes);
    $tooMany = array_fill(0, 201, $line);
    $tooManyBody = Scenario::delivery($tooMany);

    $response = recordDelivery($this, $noLines);
    Scenario::assertFieldError($response, 'lines', 'lines.required');
    $response = recordDelivery($this, $tooManyBody);
    Scenario::assertFieldError($response, 'lines', 'lines.too_many');
});

it('refuses a total too large for the app to hold exactly', function () {
    $lines = [];

    for ($index = 0; $index < 10; $index++) {
        $product = Scenario::product($this->shop);
        $lines[] = Scenario::line($product, quantity: 999_999, unitPrice: 999_999_999);
    }

    $body = Scenario::delivery($lines);

    $response = recordDelivery($this, $body);
    Scenario::assertFieldError($response, 'lines', 'lines.total_too_high');
    nothingRecorded();
});

it('refuses a line id already used by another delivery', function () {
    $firstLine = Scenario::line($this->tomatoes);
    $firstBody = Scenario::delivery([$firstLine]);
    recordDelivery($this, $firstBody)->assertCreated();
    $reusedLine = [...Scenario::line($this->harissa), 'id' => $firstLine['id']];
    $secondBody = Scenario::delivery([$reusedLine]);

    $response = recordDelivery($this, $secondBody);
    Scenario::assertFieldError($response, 'lines.0.id', 'id.taken');

    $harissaStock = Scenario::stockOf($this->harissa);
    expect($harissaStock)->toBe(0);
});

it('refuses another shop products, supplier and delivery id', function () {
    ['shop' => $otherShop, 'token' => $otherToken] = Scenario::merchantWithShop();
    $foreignProduct = Scenario::product($otherShop);
    $foreignSupplier = Scenario::supplier($otherShop);
    $foreignSupplierId = $foreignSupplier->uuid();
    $foreignLine = Scenario::line($foreignProduct);
    $myLine = Scenario::line($this->tomatoes);

    $productBody = Scenario::delivery([$foreignLine]);
    $response = recordDelivery($this, $productBody);
    Scenario::assertFieldError($response, 'lines.0.product_id', 'product_id.unknown');

    $supplierBody = Scenario::delivery([$myLine], ['supplier_id' => $foreignSupplierId]);
    $response = recordDelivery($this, $supplierBody);
    Scenario::assertFieldError($response, 'supplier_id', 'supplier_id.unknown');

    $otherLine = Scenario::line($foreignProduct);
    $foreignDelivery = Scenario::delivery([$otherLine]);
    recordDelivery($this, $foreignDelivery, $otherToken)->assertCreated();
    $reusedIdBody = Scenario::delivery([$myLine], ['id' => $foreignDelivery['id']]);
    $response = recordDelivery($this, $reusedIdBody);
    Scenario::assertFieldError($response, 'id', 'id.taken');

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    expect($tomatoStock)->toBe(0);
});

it('refuses a request with no session and a merchant with no shop', function () {
    $line = Scenario::line($this->tomatoes);
    $body = Scenario::delivery([$line]);

    $this->postJson('/api/v1/deliveries', $body)->assertUnauthorized();

    $noShopToken = Scenario::tokenOfMerchantWithoutShop();
    recordDelivery($this, $body, $noShopToken)->assertForbidden()->assertExactJson(['error' => ['code' => 'shop.required']]);
});
