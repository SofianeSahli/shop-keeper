<?php

use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
    $this->benAli = Scenario::supplier($this->shop, ['name' => 'Ben Ali']);
    $this->benAliId = $this->benAli->uuid();
    $this->moulin = Scenario::supplier($this->shop, ['name' => 'Moulin']);
    $this->moulinId = $this->moulin->uuid();
    $this->product = Scenario::product($this->shop);
});

function deliveryOn(object $test, string $date, ?string $supplierId): array
{
    $line = Scenario::line($test->product, quantity: 2, unitPrice: 1500);
    $body = Scenario::delivery([$line], ['delivery_date' => $date, 'supplier_id' => $supplierId]);
    Scenario::send($test, 'POST', 'deliveries', $body)->assertCreated();

    return $body;
}

it('lists deliveries latest date first, in pages', function () {
    $old = deliveryOn($this, '2026-09-01', $this->benAliId);
    $recent = deliveryOn($this, '2026-11-15', $this->moulinId);
    $middle = deliveryOn($this, '2026-10-20', null);
    $cancellationId = Scenario::newId();
    Scenario::send($this, 'POST', "deliveries/{$middle['id']}/cancellation", ['id' => $cancellationId])->assertCreated();

    $firstPage = Scenario::send($this, 'GET', 'deliveries?per_page=2')->assertOk();
    $firstIds = $firstPage->json('data.*.id');
    expect($firstIds)->toBe([$recent['id'], $middle['id']]);
    $firstPage
        ->assertJsonPath('data.0.supplier.name', 'Moulin')
        ->assertJsonPath('data.0.total', 3000)
        ->assertJsonPath('data.0.line_count', 1)
        ->assertJsonPath('data.0.cancelled_at', null)
        ->assertJsonMissingPath('data.0.lines');
    $middleCancelledAt = $firstPage->json('data.1.cancelled_at');
    expect($middleCancelledAt)->not->toBeNull();

    $cursor = $firstPage->json('meta.next_cursor');
    $secondPage = Scenario::send($this, 'GET', "deliveries?per_page=2&cursor={$cursor}")->assertOk();
    $secondIds = $secondPage->json('data.*.id');
    expect($secondIds)->toBe([$old['id']]);
});

it('keeps a stable order for deliveries of the same day', function () {
    $ids = [];

    for ($index = 0; $index < 5; $index++) {
        $delivery = deliveryOn($this, '2026-11-01', null);
        $ids[] = $delivery['id'];
    }

    $firstPage = Scenario::send($this, 'GET', 'deliveries?per_page=3')->assertOk();
    $cursor = $firstPage->json('meta.next_cursor');
    $secondPage = Scenario::send($this, 'GET', "deliveries?per_page=3&cursor={$cursor}")->assertOk();
    $firstIds = $firstPage->json('data.*.id');
    $secondIds = $secondPage->json('data.*.id');
    $listedIds = [...$firstIds, ...$secondIds];

    rsort($ids);
    expect($listedIds)->toBe($ids);
});

it('lists one supplier deliveries', function () {
    $fromBenAli = deliveryOn($this, '2026-11-01', $this->benAliId);
    deliveryOn($this, '2026-11-02', $this->moulinId);
    deliveryOn($this, '2026-11-03', null);

    $response = Scenario::send($this, 'GET', "deliveries?supplier_id={$this->benAliId}")->assertOk();
    $ids = $response->json('data.*.id');
    expect($ids)->toBe([$fromBenAli['id']]);
});

it('gives an empty page for another shop supplier and refuses a bad filter', function () {
    deliveryOn($this, '2026-11-01', $this->benAliId);
    ['shop' => $otherShop] = Scenario::merchantWithShop();
    $foreignSupplier = Scenario::supplier($otherShop);
    $foreignSupplierId = $foreignSupplier->uuid();

    Scenario::send($this, 'GET', "deliveries?supplier_id={$foreignSupplierId}")->assertOk()->assertJsonCount(0, 'data');
    $badFilter = Scenario::send($this, 'GET', 'deliveries?supplier_id=nope');
    Scenario::assertFieldError($badFilter, 'supplier_id', 'supplier_id.invalid');
    $badPageSize = Scenario::send($this, 'GET', 'deliveries?per_page=500');
    Scenario::assertFieldError($badPageSize, 'per_page', 'per_page.invalid');
});

it('reads one delivery with its lines', function () {
    $delivery = deliveryOn($this, '2026-11-01', $this->benAliId);
    $line = $delivery['lines'][0];

    Scenario::send($this, 'GET', "deliveries/{$delivery['id']}")
        ->assertOk()
        ->assertJsonPath('data.lines', [[...$line, 'amount' => 3000]])
        ->assertJsonPath('data.payment', null);
});

it('answers another shop delivery and an unknown id as not found', function (string $case) {
    ['shop' => $otherShop, 'token' => $otherToken] = Scenario::merchantWithShop();
    $foreignProduct = Scenario::product($otherShop);
    $foreignLine = Scenario::line($foreignProduct);
    $foreignDelivery = Scenario::delivery([$foreignLine]);
    Scenario::send($this, 'POST', 'deliveries', $foreignDelivery, $otherToken)->assertCreated();
    $unknownId = Scenario::newId();
    $deliveryId = match ($case) {
        'another shop' => $foreignDelivery['id'],
        'unknown' => $unknownId,
        'not an id' => 'not-a-uuid',
    };

    Scenario::send($this, 'GET', "deliveries/{$deliveryId}")
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'delivery.not_found']]);
})->with(['another shop', 'unknown', 'not an id']);

it('refuses a request with no session and a merchant with no shop', function () {
    $this->getJson('/api/v1/deliveries')->assertUnauthorized();

    $noShopToken = Scenario::tokenOfMerchantWithoutShop();
    Scenario::send($this, 'GET', 'deliveries', [], $noShopToken)->assertForbidden();
});
