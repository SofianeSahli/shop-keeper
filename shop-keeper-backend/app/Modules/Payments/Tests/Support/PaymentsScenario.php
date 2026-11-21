<?php

namespace App\Modules\Payments\Tests\Support;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Shops\Models\Shop;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Illuminate\Testing\TestResponse;

final class PaymentsScenario
{
    public static function payment(array $fields = []): array
    {
        $id = ContactsScenario::newId();

        return ['id' => $id, 'direction' => 'paid', 'amount' => 10000, 'method' => 'cash', ...$fields];
    }

    public static function record(object $test, array $body, ?string $token = null): TestResponse
    {
        return ContactsScenario::send($test, 'POST', 'payments', $body, $token);
    }

    public static function recordPurchase(object $test, Shop $shop, ?Contact $contact, int $total, ?string $token = null): string
    {
        $product = TransactionsScenario::product($shop);
        $line = TransactionsScenario::line($product, 1, $total);
        $contactId = $contact?->uuid();
        $body = TransactionsScenario::purchase([$line], ['contact_id' => $contactId]);
        TransactionsScenario::record($test, $body, $token)->assertCreated();

        return $body['id'];
    }

    public static function cancel(object $test, string $uri, array $fields = [], ?string $token = null): TestResponse
    {
        $body = self::cancellation($fields);

        return ContactsScenario::send($test, 'POST', $uri.'/cancellation', $body, $token);
    }

    public static function balanceOf(object $test, string $contactId): int
    {
        $response = ContactsScenario::send($test, 'GET', 'contacts/'.$contactId);
        $response->assertOk();

        return $response->json('data.balance');
    }

    public static function transactionOf(object $test, string $transactionId): array
    {
        $response = ContactsScenario::send($test, 'GET', 'transactions/'.$transactionId);
        $response->assertOk();

        return $response->json('data');
    }

    public static function amountLeftOf(object $test, string $transactionId): int
    {
        $transaction = self::transactionOf($test, $transactionId);

        return $transaction['amount_left'];
    }

    public static function cancellation(array $fields = []): array
    {
        $id = ContactsScenario::newId();

        return ['id' => $id, ...$fields];
    }
}
