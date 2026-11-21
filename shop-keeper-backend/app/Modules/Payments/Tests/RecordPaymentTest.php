<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Models\Transaction;
use App\Shared\Database\BinaryUuid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->supplier = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali', 'is_supplier' => true]);
    $this->supplierId = $this->supplier->uuid();
    $this->purchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 54000);
});

it('records a payment for a purchase and lowers what is left to pay', function () {
    $body = PaymentsScenario::payment([
        'contact_id' => $this->supplierId,
        'transaction_id' => $this->purchaseId,
        'amount' => 20000,
        'method' => 'cheque',
        'payment_date' => '2026-11-20',
        'reference' => 'CHQ-0042',
    ]);

    $response = PaymentsScenario::record($this, $body);

    $createdAt = $response->json('data.created_at');
    $response->assertCreated()->assertJsonPath('data', [
        'id' => $body['id'],
        'direction' => 'paid',
        'contact' => ['id' => $this->supplierId, 'name' => 'Grossiste Ben Ali'],
        'transaction_id' => $this->purchaseId,
        'amount' => 20000,
        'method' => 'cheque',
        'payment_date' => '2026-11-20',
        'reference' => 'CHQ-0042',
        'recorded_by' => $this->account,
        'cancellation' => null,
        'created_at' => $createdAt,
    ]);
    $paymentAnswer = $response->json('data');
    $purchase = PaymentsScenario::transactionOf($this, $this->purchaseId);
    expect([$purchase['amount_paid'], $purchase['amount_left'], $purchase['payment_state']])->toBe([20000, 34000, 'partly_paid']);
    expect($purchase['payments'])->toBe([$paymentAnswer]);
});

it('keeps every payment of a purchase paid in several times, newest first', function () {
    $first = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 20000, 'payment_date' => '2026-11-01']);
    $second = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 34000, 'payment_date' => '2026-11-02']);

    PaymentsScenario::record($this, $first)->assertCreated();
    PaymentsScenario::record($this, $second)->assertCreated();

    $purchase = PaymentsScenario::transactionOf($this, $this->purchaseId);
    $paymentIds = array_column($purchase['payments'], 'id');
    expect([$purchase['amount_paid'], $purchase['amount_left'], $purchase['payment_state']])->toBe([54000, 0, 'paid']);
    expect($paymentIds)->toBe([$second['id'], $first['id']]);
});

it('refuses a linked payment larger than what is left to pay', function () {
    $first = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 20000]);
    $tooMuch = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 34001]);
    PaymentsScenario::record($this, $first)->assertCreated();

    $response = PaymentsScenario::record($this, $tooMuch);

    ContactsScenario::assertFieldError($response, 'amount', 'amount.exceeds_left');
    $paymentCount = Payment::query()->count();
    expect($paymentCount)->toBe(1);
});

it('records a payment on account that changes no purchase', function (string $direction) {
    $body = PaymentsScenario::payment(['contact_id' => $this->supplierId, 'direction' => $direction, 'amount' => 900000]);

    PaymentsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.transaction_id', null)
        ->assertJsonPath('data.direction', $direction)
        ->assertJsonPath('data.contact.id', $this->supplierId);

    $amountLeft = PaymentsScenario::amountLeftOf($this, $this->purchaseId);
    expect($amountLeft)->toBe(54000);
})->with(['paid', 'received']);

it('pays a purchase with no contact, and the payment has no contact', function () {
    $marketPurchaseId = PaymentsScenario::recordPurchase($this, $this->shop, null, 8000);
    $body = PaymentsScenario::payment(['transaction_id' => $marketPurchaseId, 'amount' => 8000]);

    PaymentsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.contact', null)
        ->assertJsonPath('data.transaction_id', $marketPurchaseId);
});

it('takes the contact of the purchase when none is sent', function () {
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId]);

    PaymentsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.contact', ['id' => $this->supplierId, 'name' => 'Grossiste Ben Ali']);
});

it('refuses a payment with neither contact nor purchase', function () {
    $body = PaymentsScenario::payment();

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'contact_id', 'contact_id.required');
});

it('refuses a contact different from the purchase contact', function (string $case) {
    $otherContact = ContactsScenario::contact($this->shop);
    $marketPurchaseId = PaymentsScenario::recordPurchase($this, $this->shop, null, 8000);
    $transactionId = $case === 'other contact' ? $this->purchaseId : $marketPurchaseId;
    $contactId = $case === 'other contact' ? $otherContact->uuid() : $this->supplierId;
    $body = PaymentsScenario::payment(['transaction_id' => $transactionId, 'contact_id' => $contactId]);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'contact_id', 'contact_id.mismatch');
})->with(['other contact', 'purchase without contact']);

it('refuses money received for a purchase', function () {
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'direction' => 'received']);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'direction', 'direction.mismatch');
});

it('refuses a cancelled purchase', function () {
    $purchaseBytes = BinaryUuid::toBytes($this->purchaseId);
    $cancellationId = ContactsScenario::newId();
    $cancellationBytes = BinaryUuid::toBytes($cancellationId);
    $cancelledAt = now();
    DB::table('transaction_cancellations')->insert([
        'id' => $cancellationBytes,
        'shop_id' => $this->shop->id,
        'transaction_id' => $purchaseBytes,
        'cancelled_by' => $this->account,
        'created_at' => $cancelledAt,
    ]);
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId]);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'transaction_id', 'transaction_id.cancelled');
});

it('refuses a purchase or contact of another shop like an unknown one', function (string $field, string $whose) {
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $foreignContact = ContactsScenario::contact($otherShop);
    $foreignPurchaseId = PaymentsScenario::recordPurchase($this, $otherShop, $foreignContact, 5000, $otherToken);
    $foreignIds = ['contact_id' => $foreignContact->uuid(), 'transaction_id' => $foreignPurchaseId];
    $unknownId = ContactsScenario::newId();
    $id = $whose === 'other shop' ? $foreignIds[$field] : $unknownId;
    $body = PaymentsScenario::payment([$field => $id]);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, $field, $field.'.unknown');
})->with([
    ['contact_id', 'other shop'],
    ['contact_id', 'unknown'],
    ['transaction_id', 'other shop'],
    ['transaction_id', 'unknown'],
]);

it('answers a stable code for each wrong field', function (array $fields, string $field, string $code) {
    $body = [...PaymentsScenario::payment(['contact_id' => $this->supplierId]), ...$fields];

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
    $paymentCount = Payment::query()->count();
    expect($paymentCount)->toBe(0);
})->with(fn (): array => [
    'no id' => [['id' => null], 'id', 'id.required'],
    'id not a uuid' => [['id' => 'abc'], 'id', 'id.invalid'],
    'no direction' => [['direction' => null], 'direction', 'direction.required'],
    'unknown direction' => [['direction' => 'lent'], 'direction', 'direction.invalid'],
    'contact not a uuid' => [['contact_id' => 'abc'], 'contact_id', 'contact_id.invalid'],
    'purchase not a uuid' => [['transaction_id' => 'abc'], 'transaction_id', 'transaction_id.invalid'],
    'no amount' => [['amount' => null], 'amount', 'amount.required'],
    'amount not whole' => [['amount' => 10.5], 'amount', 'amount.integer'],
    'amount as text' => [['amount' => '100'], 'amount', 'amount.integer'],
    'amount of 0' => [['amount' => 0], 'amount', 'amount.too_low'],
    'negative amount' => [['amount' => -5], 'amount', 'amount.too_low'],
    'amount too high' => [['amount' => 1_000_000_000_000], 'amount', 'amount.too_high'],
    'no method' => [['method' => null], 'method', 'method.required'],
    'unknown method' => [['method' => 'bitcoin'], 'method', 'method.invalid'],
    'date not a date' => [['payment_date' => '2026-13-01'], 'payment_date', 'payment_date.invalid'],
    'reference not text' => [['reference' => 42], 'reference', 'reference.invalid'],
    'reference too long' => [['reference' => str_repeat('R', 51)], 'reference', 'reference.too_long'],
]);

it('dates a payment today in the shop time zone and refuses a future date', function () {
    $lateEvening = CarbonImmutable::parse('2026-11-21 23:30:00', 'UTC');
    $this->travelTo($lateEvening);
    $today = PaymentsScenario::payment(['contact_id' => $this->supplierId]);
    $tomorrow = PaymentsScenario::payment(['contact_id' => $this->supplierId, 'payment_date' => '2026-11-23']);

    PaymentsScenario::record($this, $today)->assertCreated()->assertJsonPath('data.payment_date', '2026-11-22');
    $refusal = PaymentsScenario::record($this, $tomorrow);

    ContactsScenario::assertFieldError($refusal, 'payment_date', 'payment_date.in_future');
});

it('answers the existing payment when the same id is sent again', function () {
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId, 'amount' => 30000]);
    PaymentsScenario::record($this, $body)->assertCreated();

    PaymentsScenario::record($this, $body)->assertOk()->assertJsonPath('data.id', $body['id']);

    $paymentCount = Payment::query()->count();
    $amountLeft = PaymentsScenario::amountLeftOf($this, $this->purchaseId);
    expect([$paymentCount, $amountLeft])->toBe([1, 24000]);
});

it('refuses an id used by another shop without saying more', function () {
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $foreignContact = ContactsScenario::contact($otherShop);
    $foreignContactId = $foreignContact->uuid();
    $foreign = PaymentsScenario::payment(['contact_id' => $foreignContactId]);
    PaymentsScenario::record($this, $foreign, $otherToken)->assertCreated();
    $body = PaymentsScenario::payment(['id' => $foreign['id'], 'contact_id' => $this->supplierId]);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'id', 'id.taken');
    $response->assertJsonMissingPath('data');
});

it('accepts only one of two payments racing for the last amount left', function () {
    $purchaseId = $this->purchaseId;
    $shopId = $this->shop->id;
    $account = $this->account;
    $supplierBytes = BinaryUuid::toBytes($this->supplierId);
    $competitorIsIn = false;
    Transaction::retrieved(function (Transaction $transaction) use ($purchaseId, $shopId, $account, $supplierBytes, &$competitorIsIn): void {
        $retrievedId = $transaction->uuid();

        if ($competitorIsIn || $retrievedId !== $purchaseId) {
            return;
        }

        $competitorIsIn = true;
        $competitorId = ContactsScenario::newId();
        $competitorBytes = BinaryUuid::toBytes($competitorId);
        $purchaseBytes = BinaryUuid::toBytes($purchaseId);
        $createdAt = now();
        DB::table('payments')->insert([
            'id' => $competitorBytes,
            'shop_id' => $shopId,
            'direction' => 'paid',
            'contact_id' => $supplierBytes,
            'transaction_id' => $purchaseBytes,
            'amount' => 54000,
            'method' => 'cash',
            'payment_date' => '2026-11-21',
            'created_by' => $account,
            'created_at' => $createdAt,
        ]);
    });
    $body = PaymentsScenario::payment(['transaction_id' => $purchaseId, 'amount' => 54000]);

    $response = PaymentsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'amount', 'amount.exceeds_left');
    $isRecorded = Payment::query()->whereUuid($body['id'])->exists();
    expect($competitorIsIn)->toBeTrue();
    expect($isRecorded)->toBeFalse();
});

it('reads one payment of the shop', function () {
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId]);
    PaymentsScenario::record($this, $body)->assertCreated();

    ContactsScenario::send($this, 'GET', 'payments/'.$body['id'])
        ->assertOk()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.amount', 10000);
});

it('answers not found for an unknown payment or one of another shop', function (string $whose) {
    ['token' => $otherToken] = ContactsScenario::merchantWithShop();
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId]);
    PaymentsScenario::record($this, $body)->assertCreated();
    $paymentId = match ($whose) {
        'other shop' => $body['id'],
        'unknown' => ContactsScenario::newId(),
        'not a uuid' => 'not-a-uuid',
    };

    ContactsScenario::send($this, 'GET', 'payments/'.$paymentId, [], $otherToken)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'payment.not_found']]);
})->with(['other shop', 'unknown', 'not a uuid']);

it('refuses recording and reading without a session or without a shop', function (string $method, string $uri) {
    $body = PaymentsScenario::payment(['transaction_id' => $this->purchaseId]);
    $path = str_replace('{payment}', $body['id'], $uri);
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, $method, $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, $method, $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
})->with([
    ['POST', 'payments'],
    ['GET', 'payments/{payment}'],
]);
