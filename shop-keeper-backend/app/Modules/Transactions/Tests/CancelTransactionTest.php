<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Models\TransactionCancellation;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->supplier = ContactsScenario::contact($this->shop);
    $this->supplierId = $this->supplier->uuid();
    $this->tomatoes = TransactionsScenario::product($this->shop);
    $this->flour = TransactionsScenario::product($this->shop);
    $tomatoLine = TransactionsScenario::line($this->tomatoes, 24, 1200);
    $flourLine = TransactionsScenario::line($this->flour, 10, 2520);
    $this->lines = [$tomatoLine, $flourLine];
    $this->purchase = TransactionsScenario::purchase($this->lines, ['contact_id' => $this->supplierId]);
    TransactionsScenario::record($this, $this->purchase)->assertCreated();
    $this->purchaseUri = 'transactions/'.$this->purchase['id'];
});

it('cancels a purchase with a reason, the account and the time, and takes its stock back', function () {
    $now = CarbonImmutable::parse('2026-11-21 10:05:00', 'UTC');
    $this->travelTo($now);

    $response = PaymentsScenario::cancel($this, $this->purchaseUri, ['reason' => '  wrong supplier  ']);

    $response->assertCreated()
        ->assertJsonPath('data.id', $this->purchase['id'])
        ->assertJsonPath('data.cancellation', [
            'reason' => 'wrong supplier',
            'cancelled_by' => $this->account,
            'cancelled_at' => '2026-11-21T10:05:00Z',
        ]);
    $tomatoStock = TransactionsScenario::stockOf($this->tomatoes);
    $flourStock = TransactionsScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([0, 0]);

    $cancellations = StockMovement::query()->where('kind', StockMovementKind::PurchaseCancellation)->orderBy('quantity')->get();
    $rows = $cancellations->map(fn (StockMovement $movement): array => [$movement->quantity, $movement->source_id])->all();
    expect($rows)->toBe([[-24, $this->lines[0]['id']], [-10, $this->lines[1]['id']]]);

    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);
    expect($balance)->toBe(0);
});

it('cancels a purchase without a reason', function () {
    PaymentsScenario::cancel($this, $this->purchaseUri)
        ->assertCreated()
        ->assertJsonPath('data.cancellation.reason', null);
});

it('changes nothing when a cancelled purchase is cancelled again', function (string $repeat) {
    $first = PaymentsScenario::cancellation(['reason' => 'first']);
    ContactsScenario::send($this, 'POST', $this->purchaseUri.'/cancellation', $first)->assertCreated();
    $second = $repeat === 'same id' ? $first : PaymentsScenario::cancellation(['reason' => 'second']);

    ContactsScenario::send($this, 'POST', $this->purchaseUri.'/cancellation', $second)
        ->assertOk()
        ->assertJsonPath('data.cancellation.reason', 'first');

    $cancellationCount = TransactionCancellation::query()->count();
    $tomatoStock = TransactionsScenario::stockOf($this->tomatoes);
    $movementCount = StockMovement::query()->count();
    expect([$cancellationCount, $tomatoStock, $movementCount])->toBe([1, 0, 4]);
})->with(['same id', 'new id']);

it('refuses to cancel a purchase with a payment still counting, and allows it once the payment is cancelled', function () {
    $payment = PaymentsScenario::payment(['transaction_id' => $this->purchase['id'], 'amount' => 20000]);
    PaymentsScenario::record($this, $payment)->assertCreated();

    PaymentsScenario::cancel($this, $this->purchaseUri)
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'transaction.has_payments']]);
    $tomatoStock = TransactionsScenario::stockOf($this->tomatoes);
    $cancellationCount = TransactionCancellation::query()->count();
    expect([$tomatoStock, $cancellationCount])->toBe([24, 0]);

    PaymentsScenario::cancel($this, 'payments/'.$payment['id'])->assertCreated();
    PaymentsScenario::cancel($this, $this->purchaseUri)->assertCreated();
});

it('refuses a cancellation id already used, even by another shop', function (string $whose) {
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $owner = $whose === 'this shop' ? $this->shop : $otherShop;
    $token = $whose === 'this shop' ? $this->token : $otherToken;
    $otherProduct = TransactionsScenario::product($owner);
    $otherLine = TransactionsScenario::line($otherProduct);
    $otherPurchase = TransactionsScenario::purchase([$otherLine]);
    TransactionsScenario::record($this, $otherPurchase, $token)->assertCreated();
    $usedCancellation = PaymentsScenario::cancellation();
    ContactsScenario::send($this, 'POST', 'transactions/'.$otherPurchase['id'].'/cancellation', $usedCancellation, $token)->assertCreated();

    $response = ContactsScenario::send($this, 'POST', $this->purchaseUri.'/cancellation', $usedCancellation);

    ContactsScenario::assertFieldError($response, 'id', 'id.taken');
    $tomatoStock = TransactionsScenario::stockOf($this->tomatoes);
    expect($tomatoStock)->toBe(24);
})->with(['this shop', 'other shop']);

it('answers a stable code for each wrong cancellation field', function (array $fields, string $field, string $code) {
    $body = [...PaymentsScenario::cancellation(), ...$fields];

    $response = ContactsScenario::send($this, 'POST', $this->purchaseUri.'/cancellation', $body);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with(fn (): array => [
    'no id' => [['id' => null], 'id', 'id.required'],
    'id not a uuid' => [['id' => 'x'], 'id', 'id.invalid'],
    'reason not text' => [['reason' => ['why']], 'reason', 'reason.invalid'],
    'reason too long' => [['reason' => str_repeat('r', 256)], 'reason', 'reason.too_long'],
]);

it('answers not found for a purchase of another shop and cancels nothing', function () {
    ['token' => $otherToken] = ContactsScenario::merchantWithShop();

    PaymentsScenario::cancel($this, $this->purchaseUri, [], $otherToken)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'transaction.not_found']]);

    $cancellationCount = TransactionCancellation::query()->count();
    expect($cancellationCount)->toBe(0);
});

it('has no way to change or delete a purchase or its lines', function (string $method, string $uri) {
    $path = str_replace('{transaction}', $this->purchase['id'], $uri);

    ContactsScenario::send($this, $method, $path, ['total' => 1])
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'request.method_not_allowed']]);
})->with([
    ['PATCH', 'transactions/{transaction}'],
    ['PUT', 'transactions/{transaction}'],
    ['DELETE', 'transactions/{transaction}'],
    ['DELETE', 'transactions/{transaction}/cancellation'],
]);

it('refuses cancelling without a session or without a shop', function () {
    $body = PaymentsScenario::cancellation();
    $path = $this->purchaseUri.'/cancellation';
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'POST', $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'POST', $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
