<?php

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Models\PaymentCancellation;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Models\TransactionCancellation;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;

function shopWithRecords(object $test): array
{
    ['shop' => $shop, 'token' => $token] = ContactsScenario::merchantWithShop();
    $contact = ContactsScenario::contact($shop, ['name' => 'Private supplier', 'phone' => '+21698123456']);
    $contactId = $contact->uuid();
    $product = TransactionsScenario::product($shop);
    $line = TransactionsScenario::line($product, 4, 1000);
    $purchase = TransactionsScenario::purchase([$line], ['contact_id' => $contactId]);
    TransactionsScenario::record($test, $purchase, $token)->assertCreated();
    $payment = PaymentsScenario::payment(['transaction_id' => $purchase['id'], 'amount' => 1000]);
    PaymentsScenario::record($test, $payment, $token)->assertCreated();
    $paidCancellation = PaymentsScenario::cancellation();
    $onAccount = PaymentsScenario::payment(['contact_id' => $contactId]);
    PaymentsScenario::record($test, $onAccount, $token)->assertCreated();
    ContactsScenario::send($test, 'POST', 'payments/'.$onAccount['id'].'/cancellation', $paidCancellation, $token)->assertCreated();

    return [
        'shop' => $shop,
        'token' => $token,
        'contact' => $contact,
        'contact_id' => $contactId,
        'product' => $product,
        'product_id' => $product->uuid(),
        'purchase' => $purchase,
        'line_id' => $line['id'],
        'payment_id' => $payment['id'],
        'cancellation_id' => $paidCancellation['id'],
    ];
}

beforeEach(function () {
    $this->first = shopWithRecords($this);
    $this->second = shopWithRecords($this);
    $this->token = $this->second['token'];
});

it('answers another shop records as not found, for reading, changing and cancelling', function (string $method, string $uri, string $code) {
    $path = strtr($uri, [
        '{contact}' => $this->first['contact_id'],
        '{transaction}' => $this->first['purchase']['id'],
        '{payment}' => $this->first['payment_id'],
    ]);
    $cancellation = PaymentsScenario::cancellation();
    $body = $method === 'PATCH' ? ['name' => 'Taken over'] : $cancellation;

    ContactsScenario::send($this, $method, $path, $body)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => $code]]);
})->with([
    ['GET', 'contacts/{contact}', 'contact.not_found'],
    ['PATCH', 'contacts/{contact}', 'contact.not_found'],
    ['GET', 'transactions/{transaction}', 'transaction.not_found'],
    ['POST', 'transactions/{transaction}/cancellation', 'transaction.not_found'],
    ['GET', 'payments/{payment}', 'payment.not_found'],
    ['POST', 'payments/{payment}/cancellation', 'payment.not_found'],
]);

it('leaves another shop records unchanged after those attempts', function () {
    $contactPath = 'contacts/'.$this->first['contact_id'];
    $purchasePath = 'transactions/'.$this->first['purchase']['id'];
    $paymentPath = 'payments/'.$this->first['payment_id'];
    ContactsScenario::send($this, 'PATCH', $contactPath, ['name' => 'Taken over']);
    PaymentsScenario::cancel($this, $paymentPath);
    PaymentsScenario::cancel($this, $purchasePath);

    $contactName = Contact::query()->whereUuid($this->first['contact_id'])->value('name');
    $transactionCancellations = TransactionCancellation::query()->count();
    $paymentCancellations = PaymentCancellation::query()->count();
    $stock = TransactionsScenario::stockOf($this->first['product']);
    expect([$contactName, $transactionCancellations, $paymentCancellations, $stock])->toBe(['Private supplier', 0, 2, 4]);
});

it('lists only the shop records, and an empty page when filtering on another shop record', function () {
    $lists = [
        'contacts',
        'transactions',
        'payments',
        'transactions?contact_id='.$this->first['contact_id'],
        'payments?contact_id='.$this->first['contact_id'],
        'payments?transaction_id='.$this->first['purchase']['id'],
    ];
    $ownIds = [$this->second['contact_id'], $this->second['purchase']['id'], $this->second['payment_id']];
    $foreignIds = [$this->first['contact_id'], $this->first['purchase']['id'], $this->first['payment_id']];

    foreach ($lists as $index => $list) {
        $response = ContactsScenario::send($this, 'GET', $list);
        $response->assertOk();
        $rows = $response->json('data');
        $ids = array_column($rows, 'id');
        $foreignListed = array_intersect($ids, $foreignIds);
        expect($foreignListed)->toBe([]);

        if ($index >= 3) {
            expect($ids)->toBe([]);
        } else {
            $ownListed = array_intersect($ids, $ownIds);
            expect($ownListed)->not->toBe([]);
        }
    }
});

it('refuses another shop contact, product or purchase like unknown ones', function (string $case) {
    $ownLine = TransactionsScenario::line($this->second['product']);
    $foreignLine = TransactionsScenario::line($this->first['product']);
    [$uri, $body, $field, $code] = match ($case) {
        'contact on a purchase' => ['transactions', TransactionsScenario::purchase([$ownLine], ['contact_id' => $this->first['contact_id']]), 'contact_id', 'contact_id.unknown'],
        'product on a purchase' => ['transactions', TransactionsScenario::purchase([$foreignLine]), 'lines.0.product_id', 'product_id.unknown'],
        'contact on a payment' => ['payments', PaymentsScenario::payment(['contact_id' => $this->first['contact_id']]), 'contact_id', 'contact_id.unknown'],
        'purchase on a payment' => ['payments', PaymentsScenario::payment(['transaction_id' => $this->first['purchase']['id']]), 'transaction_id', 'transaction_id.unknown'],
    };

    $response = ContactsScenario::send($this, 'POST', $uri, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with(['contact on a purchase', 'product on a purchase', 'contact on a payment', 'purchase on a payment']);

it('refuses every id another shop already uses, and reveals nothing about it', function (string $case) {
    $ownLine = TransactionsScenario::line($this->second['product']);
    $ownPaymentId = $this->second['payment_id'];
    $lineWithForeignId = [...$ownLine, 'id' => $this->first['line_id']];
    [$uri, $body, $field] = match ($case) {
        'contact' => ['contacts', ContactsScenario::creation(['id' => $this->first['contact_id'], 'name' => 'New name']), 'id'],
        'purchase' => ['transactions', TransactionsScenario::purchase([$ownLine], ['id' => $this->first['purchase']['id']]), 'id'],
        'line' => ['transactions', TransactionsScenario::purchase([$lineWithForeignId]), 'lines.0.id'],
        'payment' => ['payments', PaymentsScenario::payment(['id' => $this->first['payment_id'], 'contact_id' => $this->second['contact_id']]), 'id'],
        'cancellation' => ['payments/'.$ownPaymentId.'/cancellation', PaymentsScenario::cancellation(['id' => $this->first['cancellation_id']]), 'id'],
    };

    $response = ContactsScenario::send($this, 'POST', $uri, $body);

    $response->assertStatus(422)->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [$field => ['id.taken']]]]);
})->with(['contact', 'purchase', 'line', 'payment', 'cancellation']);
