<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Models\PaymentCancellation;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->supplier = ContactsScenario::contact($this->shop);
    $this->supplierId = $this->supplier->uuid();
    $this->purchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 54000);
    $this->payment = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 20000]);
    PaymentsScenario::record($this, $this->payment)->assertCreated();
    $this->paymentUri = 'payments/'.$this->payment['id'];
});

it('cancels a payment with a reason, and the money counts again as owed', function () {
    $now = CarbonImmutable::parse('2026-11-21 10:05:00', 'UTC');
    $this->travelTo($now);

    PaymentsScenario::cancel($this, $this->paymentUri, ['reason' => 'wrong amount'])
        ->assertCreated()
        ->assertJsonPath('data.id', $this->payment['id'])
        ->assertJsonPath('data.cancellation', [
            'reason' => 'wrong amount',
            'cancelled_by' => $this->account,
            'cancelled_at' => '2026-11-21T10:05:00Z',
        ]);

    $purchase = PaymentsScenario::transactionOf($this, $this->purchaseId);
    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);
    expect([$purchase['amount_paid'], $purchase['amount_left'], $purchase['payment_state'], $balance])->toBe([0, 54000, 'unpaid', 54000]);
    expect($purchase['payments'][0]['cancellation']['reason'])->toBe('wrong amount');
});

it('cancels a payment on account and the balance goes back up', function () {
    $onAccount = PaymentsScenario::payment(['contact_id' => $this->supplierId, 'amount' => 5000]);
    PaymentsScenario::record($this, $onAccount)->assertCreated();

    PaymentsScenario::cancel($this, 'payments/'.$onAccount['id'])
        ->assertCreated()
        ->assertJsonPath('data.cancellation.reason', null);

    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);
    expect($balance)->toBe(34000);
});

it('changes nothing when a cancelled payment is cancelled again', function (string $repeat) {
    $first = PaymentsScenario::cancellation(['reason' => 'first']);
    ContactsScenario::send($this, 'POST', $this->paymentUri.'/cancellation', $first)->assertCreated();
    $second = $repeat === 'same id' ? $first : PaymentsScenario::cancellation(['reason' => 'second']);

    ContactsScenario::send($this, 'POST', $this->paymentUri.'/cancellation', $second)
        ->assertOk()
        ->assertJsonPath('data.cancellation.reason', 'first');

    $cancellationCount = PaymentCancellation::query()->count();
    $amountLeft = PaymentsScenario::amountLeftOf($this, $this->purchaseId);
    expect([$cancellationCount, $amountLeft])->toBe([1, 54000]);
})->with(['same id', 'new id']);

it('refuses a cancellation id already used by another payment cancellation', function () {
    $other = PaymentsScenario::payment(['contact_id' => $this->supplierId]);
    PaymentsScenario::record($this, $other)->assertCreated();
    $usedCancellation = PaymentsScenario::cancellation();
    ContactsScenario::send($this, 'POST', 'payments/'.$other['id'].'/cancellation', $usedCancellation)->assertCreated();

    $response = ContactsScenario::send($this, 'POST', $this->paymentUri.'/cancellation', $usedCancellation);

    ContactsScenario::assertFieldError($response, 'id', 'id.taken');
    $amountLeft = PaymentsScenario::amountLeftOf($this, $this->purchaseId);
    expect($amountLeft)->toBe(34000);
});

it('answers a stable code for each wrong cancellation field', function (array $fields, string $field, string $code) {
    $body = [...PaymentsScenario::cancellation(), ...$fields];

    $response = ContactsScenario::send($this, 'POST', $this->paymentUri.'/cancellation', $body);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with(fn (): array => [
    'no id' => [['id' => null], 'id', 'id.required'],
    'id not a uuid' => [['id' => 'x'], 'id', 'id.invalid'],
    'reason not text' => [['reason' => 5], 'reason', 'reason.invalid'],
    'reason too long' => [['reason' => str_repeat('r', 256)], 'reason', 'reason.too_long'],
]);

it('answers not found for a payment of another shop and cancels nothing', function () {
    ['token' => $otherToken] = ContactsScenario::merchantWithShop();

    PaymentsScenario::cancel($this, $this->paymentUri, [], $otherToken)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'payment.not_found']]);

    $cancellationCount = PaymentCancellation::query()->count();
    expect($cancellationCount)->toBe(0);
});

it('has no way to change or delete a payment or a contact', function (string $method, string $uri) {
    $path = str_replace(['{payment}', '{contact}'], [$this->payment['id'], $this->supplierId], $uri);

    ContactsScenario::send($this, $method, $path, ['amount' => 1])
        ->assertStatus(405)
        ->assertExactJson(['error' => ['code' => 'request.method_not_allowed']]);
})->with([
    ['PATCH', 'payments/{payment}'],
    ['PUT', 'payments/{payment}'],
    ['DELETE', 'payments/{payment}'],
    ['DELETE', 'payments/{payment}/cancellation'],
    ['DELETE', 'contacts/{contact}'],
]);

it('refuses cancelling without a session or without a shop', function () {
    $body = PaymentsScenario::cancellation();
    $path = $this->paymentUri.'/cancellation';
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'POST', $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'POST', $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
