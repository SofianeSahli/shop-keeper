<?php

use App\Modules\Purchasing\Models\SupplierPayment;
use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
    $this->supplier = Scenario::supplier($this->shop, ['name' => 'Ben Ali']);
    $this->supplierId = $this->supplier->uuid();
    $this->product = Scenario::product($this->shop);
    $line = Scenario::line($this->product, quantity: 10, unitPrice: 5000);
    $delivery = Scenario::delivery([$line], ['supplier_id' => $this->supplierId]);
    Scenario::send($this, 'POST', 'deliveries', $delivery)->assertCreated();
});

function recordPayment(object $test, array $body, ?string $token = null)
{
    return Scenario::send($test, 'POST', 'supplier-payments', $body, $token);
}

function balanceOfSupplier(object $test): int
{
    $response = Scenario::send($test, 'GET', "suppliers/{$test->supplierId}");

    return $response->json('data.balance');
}

it('records a payment and lowers the balance', function () {
    $body = Scenario::payment($this->supplier, ['amount' => 20000, 'method' => 'transfer', 'payment_date' => '2026-11-01', 'reference' => 'VIR-88']);

    recordPayment($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.supplier', ['id' => $this->supplierId, 'name' => 'Ben Ali'])
        ->assertJsonPath('data.amount', 20000)
        ->assertJsonPath('data.method', 'transfer')
        ->assertJsonPath('data.payment_date', '2026-11-01')
        ->assertJsonPath('data.reference', 'VIR-88')
        ->assertJsonPath('data.delivery_id', null)
        ->assertJsonPath('data.cancelled_at', null);

    $balance = balanceOfSupplier($this);
    expect($balance)->toBe(30000);
});

it('dates a payment today in the shop time zone and refuses a future date', function () {
    $lateEveningInUtc = CarbonImmutable::parse('2026-11-19 23:30:00', 'UTC');
    $this->travelTo($lateEveningInUtc);
    $todayBody = Scenario::payment($this->supplier);
    $futureBody = Scenario::payment($this->supplier, ['payment_date' => '2026-11-21']);

    recordPayment($this, $todayBody)->assertCreated()->assertJsonPath('data.payment_date', '2026-11-20');
    $future = recordPayment($this, $futureBody);
    Scenario::assertFieldError($future, 'payment_date', 'payment_date.in_future');
});

it('refuses wrong fields with stable codes', function (array $fields, string $field, string $code) {
    $body = Scenario::payment($this->supplier, $fields);

    $response = recordPayment($this, $body);
    Scenario::assertFieldError($response, $field, $code);

    $paymentCount = SupplierPayment::query()->count();
    expect($paymentCount)->toBe(0);
})->with([
    'amount 0' => [['amount' => 0], 'amount', 'amount.too_low'],
    'negative amount' => [['amount' => -5], 'amount', 'amount.too_low'],
    'amount too high' => [['amount' => 1_000_000_000_000], 'amount', 'amount.too_high'],
    'amount not whole' => [['amount' => 10.5], 'amount', 'amount.integer'],
    'no amount' => [['amount' => null], 'amount', 'amount.required'],
    'unknown method' => [['method' => 'bitcoin'], 'method', 'method.invalid'],
    'no method' => [['method' => null], 'method', 'method.required'],
    'no supplier' => [['supplier_id' => null], 'supplier_id', 'supplier_id.required'],
    'unknown supplier' => [['supplier_id' => '0190f3a2-6b1c-7d4e-9a10-3c5e7f812b44'], 'supplier_id', 'supplier_id.unknown'],
    'bad date' => [['payment_date' => '20/11/2026'], 'payment_date', 'payment_date.invalid'],
    'long reference' => [['reference' => 'R123456789R123456789R123456789R123456789R123456789X'], 'reference', 'reference.too_long'],
]);

it('allows paying more than owed', function () {
    $body = Scenario::payment($this->supplier, ['amount' => 80000]);

    recordPayment($this, $body)->assertCreated();

    $balance = balanceOfSupplier($this);
    expect($balance)->toBe(-30000);
});

it('records once when the same payment is sent again', function () {
    $body = Scenario::payment($this->supplier, ['amount' => 1000]);

    recordPayment($this, $body)->assertCreated();
    recordPayment($this, $body)->assertOk();

    $balance = balanceOfSupplier($this);
    $paymentCount = SupplierPayment::query()->count();
    expect([$balance, $paymentCount])->toBe([49000, 1]);
});

it('cancels a payment once and the balance goes back up', function () {
    $body = Scenario::payment($this->supplier, ['amount' => 20000]);
    recordPayment($this, $body)->assertCreated();
    $firstCancellationId = Scenario::newId();
    $secondCancellationId = Scenario::newId();

    $cancelled = Scenario::send($this, 'POST', "supplier-payments/{$body['id']}/cancellation", ['id' => $firstCancellationId])->assertCreated();
    $cancelledAt = $cancelled->json('data.cancelled_at');
    expect($cancelledAt)->not->toBeNull();
    Scenario::send($this, 'POST', "supplier-payments/{$body['id']}/cancellation", ['id' => $secondCancellationId])
        ->assertOk()
        ->assertJsonPath('data.cancelled_at', $cancelledAt);

    $balance = balanceOfSupplier($this);
    expect($balance)->toBe(50000);
});

it('lists payments newest first and by supplier', function () {
    $other = Scenario::supplier($this->shop, ['name' => 'Moulin']);
    $old = Scenario::payment($this->supplier, ['payment_date' => '2026-10-01']);
    $recent = Scenario::payment($this->supplier, ['payment_date' => '2026-11-15']);
    $fromOther = Scenario::payment($other, ['payment_date' => '2026-11-10']);
    recordPayment($this, $old)->assertCreated();
    recordPayment($this, $recent)->assertCreated();
    recordPayment($this, $fromOther)->assertCreated();

    $all = Scenario::send($this, 'GET', 'supplier-payments')->assertOk();
    $allIds = $all->json('data.*.id');
    expect($allIds)->toBe([$recent['id'], $fromOther['id'], $old['id']]);

    $mine = Scenario::send($this, 'GET', "supplier-payments?supplier_id={$this->supplierId}")->assertOk();
    $mineIds = $mine->json('data.*.id');
    expect($mineIds)->toBe([$recent['id'], $old['id']]);

    Scenario::send($this, 'GET', "supplier-payments/{$recent['id']}")->assertOk()->assertJsonPath('data.id', $recent['id']);
});

it('answers another shop payment as not found and keeps its supplier unusable', function () {
    ['shop' => $otherShop, 'token' => $otherToken] = Scenario::merchantWithShop();
    $foreignSupplier = Scenario::supplier($otherShop);
    $foreignPayment = Scenario::payment($foreignSupplier);
    recordPayment($this, $foreignPayment, $otherToken)->assertCreated();
    $cancellationId = Scenario::newId();

    Scenario::send($this, 'GET', "supplier-payments/{$foreignPayment['id']}")
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'supplier_payment.not_found']]);
    Scenario::send($this, 'POST', "supplier-payments/{$foreignPayment['id']}/cancellation", ['id' => $cancellationId])->assertNotFound();

    $usingForeignSupplier = Scenario::payment($foreignSupplier);
    $response = recordPayment($this, $usingForeignSupplier);
    Scenario::assertFieldError($response, 'supplier_id', 'supplier_id.unknown');

    $reusedId = Scenario::payment($this->supplier, ['id' => $foreignPayment['id']]);
    $taken = recordPayment($this, $reusedId);
    Scenario::assertFieldError($taken, 'id', 'id.taken');
});

it('refuses a request with no session and a merchant with no shop', function () {
    $body = Scenario::payment($this->supplier);

    Scenario::sendWithoutSession($this, 'POST', 'supplier-payments', $body)->assertUnauthorized();

    $noShopToken = Scenario::tokenOfMerchantWithoutShop();
    recordPayment($this, $body, $noShopToken)->assertForbidden();
});
