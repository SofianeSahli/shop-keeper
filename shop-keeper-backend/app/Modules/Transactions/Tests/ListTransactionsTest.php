<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Illuminate\Support\Facades\DB;

function recordDatedPurchase(object $test, ?object $contact, string $date, int $total): string
{
    $product = TransactionsScenario::product($test->shop);
    $line = TransactionsScenario::line($product, 1, $total);
    $contactId = $contact?->uuid();
    $body = TransactionsScenario::purchase([$line], ['contact_id' => $contactId, 'transaction_date' => $date]);
    TransactionsScenario::record($test, $body)->assertCreated();

    return $body['id'];
}

function listedIds(object $test, string $uri): array
{
    $response = ContactsScenario::send($test, 'GET', $uri);
    $response->assertOk();
    $rows = $response->json('data');

    return array_column($rows, 'id');
}

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->supplier = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali']);
    $this->other = ContactsScenario::contact($this->shop);
});

it('lists purchases latest date first, ties by id, with their figures and without lines', function () {
    $older = recordDatedPurchase($this, $this->supplier, '2026-11-01', 5000);
    $firstOfDay = recordDatedPurchase($this, $this->other, '2026-11-10', 8000);
    $secondOfDay = recordDatedPurchase($this, null, '2026-11-10', 3000);
    $payment = PaymentsScenario::payment(['transaction_id' => $older, 'amount' => 2000]);
    PaymentsScenario::record($this, $payment)->assertCreated();

    $response = ContactsScenario::send($this, 'GET', 'transactions');

    $response->assertOk();
    $rows = $response->json('data');
    $ids = array_column($rows, 'id');
    $olderRow = $rows[2];
    expect($ids)->toBe([$secondOfDay, $firstOfDay, $older]);
    expect([$olderRow['amount_paid'], $olderRow['amount_left'], $olderRow['payment_state']])->toBe([2000, 3000, 'partly_paid']);
    expect($olderRow['contact']['name'])->toBe('Grossiste Ben Ali');
    expect($rows[0]['contact'])->toBeNull();
    expect($olderRow)->not->toHaveKeys(['lines', 'payments']);
});

it('pages purchases with a cursor', function () {
    $first = recordDatedPurchase($this, null, '2026-11-03', 1000);
    $second = recordDatedPurchase($this, null, '2026-11-02', 1000);
    $third = recordDatedPurchase($this, null, '2026-11-01', 1000);

    $firstPage = ContactsScenario::send($this, 'GET', 'transactions?per_page=2');
    $cursor = $firstPage->json('meta.next_cursor');
    $secondPageIds = listedIds($this, 'transactions?per_page=2&cursor='.$cursor);

    $firstPageRows = $firstPage->json('data');
    $firstPageIds = array_column($firstPageRows, 'id');
    expect($firstPageIds)->toBe([$first, $second]);
    expect($secondPageIds)->toBe([$third]);
});

it('filters purchases by contact and by kind', function () {
    $ofSupplier = recordDatedPurchase($this, $this->supplier, '2026-11-01', 1000);
    recordDatedPurchase($this, $this->other, '2026-11-02', 1000);
    $supplierId = $this->supplier->uuid();

    $bySupplier = listedIds($this, 'transactions?contact_id='.$supplierId);
    $purchases = listedIds($this, 'transactions?kind=purchase');

    expect($bySupplier)->toBe([$ofSupplier]);
    expect($purchases)->toHaveCount(2);
});

it('shows only the shop purchases and an empty page for another shop contact', function () {
    recordDatedPurchase($this, $this->supplier, '2026-11-01', 1000);
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $foreignContact = ContactsScenario::contact($otherShop);
    $foreignContactId = $foreignContact->uuid();
    $supplierId = $this->supplier->uuid();

    $otherShopList = ContactsScenario::send($this, 'GET', 'transactions', [], $otherToken)->assertOk()->json('data');
    $foreignFilter = listedIds($this, 'transactions?contact_id='.$foreignContactId);
    $filterOnOtherShop = ContactsScenario::send($this, 'GET', 'transactions?contact_id='.$supplierId, [], $otherToken)->assertOk()->json('data');

    expect([$otherShopList, $foreignFilter, $filterOnOtherShop])->toBe([[], [], []]);
});

it('runs the same number of queries whatever the page size', function () {
    for ($index = 0; $index < 2; $index++) {
        recordDatedPurchase($this, $this->supplier, '2026-11-01', 1000);
    }
    DB::enableQueryLog();
    ContactsScenario::send($this, 'GET', 'transactions')->assertOk();
    $smallPageLog = DB::getQueryLog();
    $smallPageQueries = count($smallPageLog);

    for ($index = 0; $index < 6; $index++) {
        recordDatedPurchase($this, $this->other, '2026-11-02', 1000);
    }
    DB::flushQueryLog();
    ContactsScenario::send($this, 'GET', 'transactions')->assertOk();
    $largePageLog = DB::getQueryLog();
    $largePageQueries = count($largePageLog);

    expect($largePageQueries)->toBe($smallPageQueries);
});

it('answers a stable code for wrong list options', function (string $query, string $field, string $code) {
    $response = ContactsScenario::send($this, 'GET', 'transactions?'.$query);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with([
    'kind' => ['kind=sale', 'kind', 'kind.invalid'],
    'contact' => ['contact_id=abc', 'contact_id', 'contact_id.invalid'],
    'page size' => ['per_page=0', 'per_page', 'per_page.invalid'],
]);

it('refuses listing without a session or without a shop', function () {
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'GET', 'transactions')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'GET', 'transactions', [], $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
