<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Facades\DB;

function cancelDirectly(string $table, string $column, string $recordId, int $shopId, int $accountId): void
{
    $cancellationId = ContactsScenario::newId();
    $cancellationBytes = BinaryUuid::toBytes($cancellationId);
    $recordBytes = BinaryUuid::toBytes($recordId);
    $cancelledAt = now();

    DB::table($table)->insert([
        'id' => $cancellationBytes,
        'shop_id' => $shopId,
        $column => $recordBytes,
        'cancelled_by' => $accountId,
        'created_at' => $cancelledAt,
    ]);
}

function recordPaymentOf(object $test, array $fields): string
{
    $body = PaymentsScenario::payment($fields);
    PaymentsScenario::record($test, $body)->assertCreated();

    return $body['id'];
}

function listedBalances(object $test): array
{
    $response = ContactsScenario::send($test, 'GET', 'contacts');
    $response->assertOk();
    $contacts = $response->json('data');

    return array_column($contacts, 'balance', 'id');
}

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->supplier = ContactsScenario::contact($this->shop, ['is_supplier' => true]);
    $this->supplierId = $this->supplier->uuid();
});

it('starts at 0', function () {
    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);

    expect($balance)->toBe(0);
});

it('adds purchases, takes off payments made and adds back payments received', function () {
    $purchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 54000);
    PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 10000);
    recordPaymentOf($this, ['transaction_id' => $purchaseId, 'amount' => 20000]);
    recordPaymentOf($this, ['contact_id' => $this->supplierId, 'amount' => 10000]);
    recordPaymentOf($this, ['contact_id' => $this->supplierId, 'direction' => 'received', 'amount' => 3000]);

    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);

    expect($balance)->toBe(37000);
});

it('goes below zero when the shop paid more than it owes', function () {
    PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 5000);
    recordPaymentOf($this, ['contact_id' => $this->supplierId, 'amount' => 15000]);

    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);

    expect($balance)->toBe(-10000);
});

it('ignores cancelled purchases and payments', function () {
    $keptPurchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 54000);
    $cancelledPurchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 9000);
    $cancelledPaymentId = recordPaymentOf($this, ['transaction_id' => $keptPurchaseId, 'amount' => 20000]);
    recordPaymentOf($this, ['transaction_id' => $keptPurchaseId, 'amount' => 4000]);
    cancelDirectly('transaction_cancellations', 'transaction_id', $cancelledPurchaseId, $this->shop->id, $this->account);
    cancelDirectly('payment_cancellations', 'payment_id', $cancelledPaymentId, $this->shop->id, $this->account);

    $balance = PaymentsScenario::balanceOf($this, $this->supplierId);
    $purchase = PaymentsScenario::transactionOf($this, $keptPurchaseId);

    expect($balance)->toBe(50000);
    expect([$purchase['amount_paid'], $purchase['amount_left']])->toBe([4000, 50000]);
});

it('counts records with no contact in no balance', function () {
    $marketPurchaseId = PaymentsScenario::recordPurchase($this, $this->shop, null, 8000);
    recordPaymentOf($this, ['transaction_id' => $marketPurchaseId, 'amount' => 3000]);

    $balances = listedBalances($this);

    expect($balances)->toBe([$this->supplierId => 0]);
});

it('counts only the records of each contact, the same in the list and alone', function () {
    $customer = ContactsScenario::contact($this->shop, ['is_customer' => true]);
    $customerId = $customer->uuid();
    PaymentsScenario::recordPurchase($this, $this->shop, $this->supplier, 7000);
    recordPaymentOf($this, ['contact_id' => $customerId, 'direction' => 'received', 'amount' => 2500]);

    $balances = listedBalances($this);
    $supplierBalance = PaymentsScenario::balanceOf($this, $this->supplierId);
    $customerBalance = PaymentsScenario::balanceOf($this, $customerId);

    expect($balances)->toEqual([$this->supplierId => 7000, $customerId => 2500]);
    expect([$supplierBalance, $customerBalance])->toBe([7000, 2500]);
});

it('shows the balance of FR-016 after any series of records and cancellations', function () {
    mt_srand(9);
    $other = ContactsScenario::contact($this->shop);
    $contacts = [$this->supplier, $other];
    $expected = [];
    $expectedPaidByPurchase = [];

    foreach ($contacts as $contact) {
        $contactId = $contact->uuid();
        $expected[$contactId] = 0;

        for ($round = 0; $round < 4; $round++) {
            $total = mt_rand(1, 50) * 1000;
            $purchaseId = PaymentsScenario::recordPurchase($this, $this->shop, $contact, $total);
            $linkedAmount = mt_rand(0, $total / 1000) * 1000;
            $onAccount = mt_rand(1, 20) * 1000;
            $direction = mt_rand(0, 1) === 1 ? 'paid' : 'received';
            $sign = $direction === 'paid' ? -1 : 1;
            $expected[$contactId] += $total + $sign * $onAccount;
            $expectedPaidByPurchase[$purchaseId] = 0;
            recordPaymentOf($this, ['contact_id' => $contactId, 'direction' => $direction, 'amount' => $onAccount]);

            if ($linkedAmount > 0) {
                $linkedPaymentId = recordPaymentOf($this, ['transaction_id' => $purchaseId, 'amount' => $linkedAmount]);
                $isPaymentCancelled = mt_rand(0, 2) === 0;

                if ($isPaymentCancelled) {
                    cancelDirectly('payment_cancellations', 'payment_id', $linkedPaymentId, $this->shop->id, $this->account);
                } else {
                    $expected[$contactId] -= $linkedAmount;
                    $expectedPaidByPurchase[$purchaseId] = $linkedAmount;
                }

                continue;
            }

            $isPurchaseCancelled = mt_rand(0, 1) === 0;

            if ($isPurchaseCancelled) {
                cancelDirectly('transaction_cancellations', 'transaction_id', $purchaseId, $this->shop->id, $this->account);
                $expected[$contactId] -= $total;
            }
        }
    }

    $listed = listedBalances($this);
    expect($listed)->toEqual($expected);

    foreach ($expected as $contactId => $expectedBalance) {
        $balance = PaymentsScenario::balanceOf($this, $contactId);
        expect($balance)->toBe($expectedBalance);
    }

    foreach ($expectedPaidByPurchase as $purchaseId => $expectedPaid) {
        $purchase = PaymentsScenario::transactionOf($this, $purchaseId);
        expect($purchase['amount_paid'])->toBe($expectedPaid);
    }
});
