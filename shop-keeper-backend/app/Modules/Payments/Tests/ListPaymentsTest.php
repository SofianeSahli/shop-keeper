<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use Illuminate\Support\Facades\DB;

function recordDatedPayment(object $test, array $fields): string
{
    $body = PaymentsScenario::payment($fields);
    PaymentsScenario::record($test, $body)->assertCreated();

    return $body['id'];
}

function listedPaymentIds(object $test, string $uri, ?string $token = null): array
{
    $response = ContactsScenario::send($test, 'GET', $uri, [], $token);
    $response->assertOk();
    $rows = $response->json('data');

    return array_column($rows, 'id');
}

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->supplier = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali']);
    $this->supplierId = $this->supplier->uuid();
    $this->other = ContactsScenario::contact($this->shop);
    $this->otherId = $this->other->uuid();
    $this->purchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 54000);
});

it('lists payments latest date first, ties by id, with their contact', function () {
    $older = recordDatedPayment($this, ['transaction_id' => $this->purchaseId, 'payment_date' => '2026-11-01']);
    $firstOfDay = recordDatedPayment($this, ['contact_id' => $this->otherId, 'payment_date' => '2026-11-10']);
    $secondOfDay = recordDatedPayment($this, ['contact_id' => $this->supplierId, 'payment_date' => '2026-11-10', 'direction' => 'received']);

    $response = ContactsScenario::send($this, 'GET', 'payments');

    $response->assertOk();
    $rows = $response->json('data');
    $ids = array_column($rows, 'id');
    expect($ids)->toBe([$secondOfDay, $firstOfDay, $older]);
    expect($rows[2]['contact'])->toBe(['id' => $this->supplierId, 'name' => 'Grossiste Ben Ali']);
    expect($rows[2]['transaction_id'])->toBe($this->purchaseId);
});

it('pages payments with a cursor', function () {
    $first = recordDatedPayment($this, ['contact_id' => $this->otherId, 'payment_date' => '2026-11-03']);
    $second = recordDatedPayment($this, ['contact_id' => $this->otherId, 'payment_date' => '2026-11-02']);
    $third = recordDatedPayment($this, ['contact_id' => $this->otherId, 'payment_date' => '2026-11-01']);

    $firstPage = ContactsScenario::send($this, 'GET', 'payments?per_page=2');
    $cursor = $firstPage->json('meta.next_cursor');
    $secondPageIds = listedPaymentIds($this, 'payments?per_page=2&cursor='.$cursor);

    $firstPageRows = $firstPage->json('data');
    $firstPageIds = array_column($firstPageRows, 'id');
    expect($firstPageIds)->toBe([$first, $second]);
    expect($secondPageIds)->toBe([$third]);
});

it('filters payments by contact and by purchase', function () {
    $linked = recordDatedPayment($this, ['transaction_id' => $this->purchaseId, 'payment_date' => '2026-11-01']);
    $onAccount = recordDatedPayment($this, ['contact_id' => $this->supplierId, 'payment_date' => '2026-11-02']);
    recordDatedPayment($this, ['contact_id' => $this->otherId]);

    $bySupplier = listedPaymentIds($this, 'payments?contact_id='.$this->supplierId);
    $byPurchase = listedPaymentIds($this, 'payments?transaction_id='.$this->purchaseId);

    expect($bySupplier)->toBe([$onAccount, $linked]);
    expect($byPurchase)->toBe([$linked]);
});

it('shows only the shop payments and an empty page for another shop filter', function () {
    recordDatedPayment($this, ['transaction_id' => $this->purchaseId]);
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $foreignContact = ContactsScenario::contact($otherShop);
    $foreignContactId = $foreignContact->uuid();

    $otherShopList = listedPaymentIds($this, 'payments', $otherToken);
    $foreignContactFilter = listedPaymentIds($this, 'payments?contact_id='.$foreignContactId);
    $purchaseFilterOnOtherShop = listedPaymentIds($this, 'payments?transaction_id='.$this->purchaseId, $otherToken);

    expect([$otherShopList, $foreignContactFilter, $purchaseFilterOnOtherShop])->toBe([[], [], []]);
});

it('runs the same number of queries whatever the page size', function () {
    recordDatedPayment($this, ['contact_id' => $this->supplierId]);
    DB::enableQueryLog();
    ContactsScenario::send($this, 'GET', 'payments')->assertOk();
    $smallPageLog = DB::getQueryLog();
    $smallPageQueries = count($smallPageLog);

    for ($index = 0; $index < 6; $index++) {
        recordDatedPayment($this, ['contact_id' => $this->otherId]);
    }
    DB::flushQueryLog();
    ContactsScenario::send($this, 'GET', 'payments')->assertOk();
    $largePageLog = DB::getQueryLog();
    $largePageQueries = count($largePageLog);

    expect($largePageQueries)->toBe($smallPageQueries);
});

it('answers a stable code for wrong list options', function (string $query, string $field, string $code) {
    $response = ContactsScenario::send($this, 'GET', 'payments?'.$query);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with([
    'contact' => ['contact_id=abc', 'contact_id', 'contact_id.invalid'],
    'purchase' => ['transaction_id=abc', 'transaction_id', 'transaction_id.invalid'],
    'page size' => ['per_page=500', 'per_page', 'per_page.invalid'],
]);

it('refuses listing without a session or without a shop', function () {
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'GET', 'payments')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'GET', 'payments', [], $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
