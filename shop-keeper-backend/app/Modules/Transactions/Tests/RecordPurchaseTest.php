<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockMovementKind;
use App\Modules\Transactions\Models\Transaction;
use App\Modules\Transactions\Models\TransactionLine;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use App\Shared\Database\BinaryUuid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->tomatoes = TransactionsScenario::product($this->shop, ['sale_price' => 1800, 'purchase_price' => 1100]);
    $this->flour = TransactionsScenario::product($this->shop, ['sale_price' => 3000, 'purchase_price' => null]);
    $this->supplier = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali', 'is_supplier' => true]);
});

it('records a purchase with its lines, total and stock', function () {
    $tomatoLine = TransactionsScenario::line($this->tomatoes, 24, 1200);
    $flourLine = TransactionsScenario::line($this->flour, 10, 2520);
    $supplierId = $this->supplier->uuid();
    $body = TransactionsScenario::purchase([$tomatoLine, $flourLine], [
        'contact_id' => $supplierId,
        'transaction_date' => '2026-11-20',
        'reference' => 'BL-2041',
    ]);

    $response = TransactionsScenario::record($this, $body);

    $response->assertCreated()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.kind', 'purchase')
        ->assertJsonPath('data.contact', ['id' => $supplierId, 'name' => 'Grossiste Ben Ali'])
        ->assertJsonPath('data.transaction_date', '2026-11-20')
        ->assertJsonPath('data.reference', 'BL-2041')
        ->assertJsonPath('data.total', 54000)
        ->assertJsonPath('data.amount_paid', 0)
        ->assertJsonPath('data.amount_left', 54000)
        ->assertJsonPath('data.payment_state', 'unpaid')
        ->assertJsonPath('data.line_count', 2)
        ->assertJsonPath('data.recorded_by', $this->account)
        ->assertJsonPath('data.cancellation', null)
        ->assertJsonPath('data.payments', [])
        ->assertJsonPath('data.lines', [
            ['id' => $tomatoLine['id'], 'product_id' => $tomatoLine['product_id'], 'quantity' => 24, 'unit_price' => 1200, 'amount' => 28800],
            ['id' => $flourLine['id'], 'product_id' => $flourLine['product_id'], 'quantity' => 10, 'unit_price' => 2520, 'amount' => 25200],
        ]);

    $tomatoStock = TransactionsScenario::stockOf($this->tomatoes);
    $flourStock = TransactionsScenario::stockOf($this->flour);
    expect([$tomatoStock, $flourStock])->toBe([24, 10]);

    $movements = StockMovement::query()->orderByDesc('quantity')->get();
    $movementRows = $movements->map(fn (StockMovement $movement): array => [$movement->kind, $movement->quantity, $movement->source_id])->all();
    expect($movementRows)->toBe([
        [StockMovementKind::Purchase, 24, $tomatoLine['id']],
        [StockMovementKind::Purchase, 10, $flourLine['id']],
    ]);
});

it('never changes the prices of the products bought', function () {
    $line = TransactionsScenario::line($this->tomatoes, 5, 9999);
    $body = TransactionsScenario::purchase([$line]);

    TransactionsScenario::record($this, $body)->assertCreated();

    $productId = $this->tomatoes->uuid();
    ContactsScenario::send($this, 'GET', 'products/'.$productId)
        ->assertOk()
        ->assertJsonPath('data.sale_price', 1800)
        ->assertJsonPath('data.purchase_price', 1100)
        ->assertJsonPath('data.stock_quantity', 5);
});

it('dates a purchase today in the shop time zone when no date is sent', function () {
    $lateEvening = CarbonImmutable::parse('2026-11-21 23:30:00', 'UTC');
    $this->travelTo($lateEvening);
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line]);

    TransactionsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.transaction_date', '2026-11-22');
});

it('keeps a past date and refuses a future one', function () {
    $noon = CarbonImmutable::parse('2026-11-21 12:00:00', 'Africa/Tunis');
    $this->travelTo($noon);
    $pastLine = TransactionsScenario::line($this->tomatoes);
    $past = TransactionsScenario::purchase([$pastLine], ['transaction_date' => '2025-01-31']);
    $futureLine = TransactionsScenario::line($this->flour);
    $future = TransactionsScenario::purchase([$futureLine], ['transaction_date' => '2026-11-22']);

    TransactionsScenario::record($this, $past)->assertCreated()->assertJsonPath('data.transaction_date', '2025-01-31');
    $refused = TransactionsScenario::record($this, $future);

    ContactsScenario::assertFieldError($refused, 'transaction_date', 'transaction_date.in_future');
});

it('records a purchase with no contact', function () {
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line]);

    TransactionsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.contact', null);
});

it('records free goods with a unit price of 0', function () {
    $line = TransactionsScenario::line($this->tomatoes, 3, 0);
    $body = TransactionsScenario::purchase([$line]);

    TransactionsScenario::record($this, $body)
        ->assertCreated()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.payment_state', 'paid');

    $stock = TransactionsScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(3);
});

it('answers the existing purchase when the same id is sent again, and changes stock once', function () {
    $line = TransactionsScenario::line($this->tomatoes, 24, 1200);
    $body = TransactionsScenario::purchase([$line]);
    TransactionsScenario::record($this, $body)->assertCreated();

    TransactionsScenario::record($this, $body)
        ->assertOk()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.total', 28800);

    $stock = TransactionsScenario::stockOf($this->tomatoes);
    $transactionCount = Transaction::query()->count();
    $movementCount = StockMovement::query()->count();
    expect([$stock, $transactionCount, $movementCount])->toBe([24, 1, 1]);
});

it('answers like the first request when an identical purchase wins the race', function () {
    $line = TransactionsScenario::line($this->tomatoes, 24, 1200);
    $body = TransactionsScenario::purchase([$line]);
    $shopId = $this->shop->id;
    $account = $this->account;
    Transaction::creating(function (Transaction $transaction) use ($body, $shopId, $account): void {
        $twinBytes = BinaryUuid::toBytes($body['id']);
        $twinExists = DB::table('transactions')->where('id', $twinBytes)->exists();

        if ($twinExists) {
            return;
        }

        $createdAt = now();
        DB::table('transactions')->insert([
            'id' => $twinBytes,
            'shop_id' => $shopId,
            'kind' => 'purchase',
            'transaction_date' => '2026-11-21',
            'total' => 28800,
            'line_count' => 1,
            'created_by' => $account,
            'created_at' => $createdAt,
        ]);
    });

    TransactionsScenario::record($this, $body)
        ->assertOk()
        ->assertJsonPath('data.id', $body['id']);

    $transactionCount = Transaction::query()->count();
    $lineCount = TransactionLine::query()->count();
    $stock = TransactionsScenario::stockOf($this->tomatoes);
    expect([$transactionCount, $lineCount, $stock])->toBe([1, 0, 0]);
});

it('records nothing when one line is wrong', function () {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    $foreignProduct = TransactionsScenario::product($otherShop);
    $goodLine = TransactionsScenario::line($this->tomatoes);
    $foreignLine = TransactionsScenario::line($foreignProduct);
    $body = TransactionsScenario::purchase([$goodLine, $foreignLine]);

    $response = TransactionsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'lines.1.product_id', 'product_id.unknown');
    $transactionCount = Transaction::query()->count();
    $lineCount = TransactionLine::query()->count();
    $stock = TransactionsScenario::stockOf($this->tomatoes);
    expect([$transactionCount, $lineCount, $stock])->toBe([0, 0, 0]);
});

it('refuses a contact of another shop like an unknown one', function (string $whose) {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    $foreignContact = ContactsScenario::contact($otherShop);
    $contactId = $whose === 'other shop' ? $foreignContact->uuid() : ContactsScenario::newId();
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line], ['contact_id' => $contactId]);

    $response = TransactionsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'contact_id', 'contact_id.unknown');
})->with(['other shop', 'unknown']);

it('refuses ids already used by another shop', function () {
    ['shop' => $otherShop, 'token' => $otherToken] = ContactsScenario::merchantWithShop();
    $foreignProduct = TransactionsScenario::product($otherShop);
    $foreignLine = TransactionsScenario::line($foreignProduct);
    $foreign = TransactionsScenario::purchase([$foreignLine]);
    TransactionsScenario::record($this, $foreign, $otherToken)->assertCreated();
    $lineWithTakenId = [...TransactionsScenario::line($this->tomatoes), 'id' => $foreignLine['id']];
    $ownLine = TransactionsScenario::line($this->tomatoes);
    $sameTransactionId = TransactionsScenario::purchase([$ownLine], ['id' => $foreign['id']]);
    $sameLineId = TransactionsScenario::purchase([$lineWithTakenId]);

    $transactionRefusal = TransactionsScenario::record($this, $sameTransactionId);
    $lineRefusal = TransactionsScenario::record($this, $sameLineId);

    ContactsScenario::assertFieldError($transactionRefusal, 'id', 'id.taken');
    ContactsScenario::assertFieldError($lineRefusal, 'lines.0.id', 'id.taken');
    $transactionRefusal->assertJsonMissingPath('data');
});

it('records a purchase of 200 lines and refuses 201', function () {
    $products = TransactionsScenario::products($this->shop, 201);
    $lines = array_map(fn ($product): array => TransactionsScenario::line($product, 1, 100), $products);
    $twoHundredLines = array_slice($lines, 0, 200);
    $accepted = TransactionsScenario::purchase($twoHundredLines);
    $tooMany = TransactionsScenario::purchase($lines);

    $refusal = TransactionsScenario::record($this, $tooMany);
    TransactionsScenario::record($this, $accepted)
        ->assertCreated()
        ->assertJsonPath('data.line_count', 200)
        ->assertJsonPath('data.total', 20000);

    ContactsScenario::assertFieldError($refusal, 'lines', 'lines.too_many');
});

it('refuses a total above the largest exact integer of the app', function () {
    $products = TransactionsScenario::products($this->shop, 10);
    $lines = array_map(fn ($product): array => TransactionsScenario::line($product, 999_999, 999_999_999), $products);
    $body = TransactionsScenario::purchase($lines);

    $response = TransactionsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, 'lines', 'lines.total_too_high');
    $transactionCount = Transaction::query()->count();
    expect($transactionCount)->toBe(0);
});

it('answers a stable code for each wrong field', function (Closure $change, string $field, string $code) {
    $tomatoLine = TransactionsScenario::line($this->tomatoes);
    $flourLine = TransactionsScenario::line($this->flour);
    $valid = TransactionsScenario::purchase([$tomatoLine, $flourLine]);
    $body = $change($valid);

    $response = TransactionsScenario::record($this, $body);

    ContactsScenario::assertFieldError($response, $field, $code);
    $transactionCount = Transaction::query()->count();
    expect($transactionCount)->toBe(0);
})->with([
    'no id' => [fn (array $body): array => [...$body, 'id' => null], 'id', 'id.required'],
    'id not a uuid' => [fn (array $body): array => [...$body, 'id' => 'abc'], 'id', 'id.invalid'],
    'no kind' => [fn (array $body): array => [...$body, 'kind' => null], 'kind', 'kind.required'],
    'sale kind' => [fn (array $body): array => [...$body, 'kind' => 'sale'], 'kind', 'kind.invalid'],
    'contact not a uuid' => [fn (array $body): array => [...$body, 'contact_id' => 'abc'], 'contact_id', 'contact_id.invalid'],
    'date not a date' => [fn (array $body): array => [...$body, 'transaction_date' => '21/11/2026'], 'transaction_date', 'transaction_date.invalid'],
    'reference not text' => [fn (array $body): array => [...$body, 'reference' => 12], 'reference', 'reference.invalid'],
    'reference too long' => [function (array $body): array {
        $reference = str_repeat('R', 51);

        return [...$body, 'reference' => $reference];
    }, 'reference', 'reference.too_long'],
    'no lines' => [fn (array $body): array => [...$body, 'lines' => []], 'lines', 'lines.required'],
    'lines not a list' => [fn (array $body): array => [...$body, 'lines' => 'many'], 'lines', 'lines.required'],
    'line not an object' => [fn (array $body): array => [...$body, 'lines' => [$body['lines'][0], 'line']], 'lines.1', 'line.invalid'],
    'line without id' => [fn (array $body): array => lineChanged($body, 1, ['id' => null]), 'lines.1.id', 'id.required'],
    'line id not a uuid' => [fn (array $body): array => lineChanged($body, 1, ['id' => 'x']), 'lines.1.id', 'id.invalid'],
    'line id twice' => [fn (array $body): array => lineChanged($body, 1, ['id' => $body['lines'][0]['id']]), 'lines.1.id', 'id.duplicate'],
    'line without product' => [fn (array $body): array => lineChanged($body, 1, ['product_id' => null]), 'lines.1.product_id', 'product_id.required'],
    'product not a uuid' => [fn (array $body): array => lineChanged($body, 1, ['product_id' => 'x']), 'lines.1.product_id', 'product_id.invalid'],
    'product twice' => [fn (array $body): array => lineChanged($body, 1, ['product_id' => $body['lines'][0]['product_id']]), 'lines.1.product_id', 'product_id.duplicate'],
    'unknown product' => [function (array $body): array {
        $unknownId = ContactsScenario::newId();

        return lineChanged($body, 1, ['product_id' => $unknownId]);
    }, 'lines.1.product_id', 'product_id.unknown'],
    'no quantity' => [fn (array $body): array => lineChanged($body, 1, ['quantity' => null]), 'lines.1.quantity', 'quantity.required'],
    'quantity not whole' => [fn (array $body): array => lineChanged($body, 1, ['quantity' => 1.5]), 'lines.1.quantity', 'quantity.integer'],
    'quantity as text' => [fn (array $body): array => lineChanged($body, 1, ['quantity' => '3']), 'lines.1.quantity', 'quantity.integer'],
    'quantity of 0' => [fn (array $body): array => lineChanged($body, 1, ['quantity' => 0]), 'lines.1.quantity', 'quantity.too_low'],
    'quantity too high' => [fn (array $body): array => lineChanged($body, 1, ['quantity' => 1_000_000]), 'lines.1.quantity', 'quantity.too_high'],
    'no unit price' => [fn (array $body): array => lineChanged($body, 1, ['unit_price' => null]), 'lines.1.unit_price', 'unit_price.required'],
    'unit price not whole' => [fn (array $body): array => lineChanged($body, 1, ['unit_price' => 1.5]), 'lines.1.unit_price', 'unit_price.integer'],
    'negative unit price' => [fn (array $body): array => lineChanged($body, 1, ['unit_price' => -1]), 'lines.1.unit_price', 'unit_price.negative'],
    'unit price too high' => [fn (array $body): array => lineChanged($body, 1, ['unit_price' => 1_000_000_000]), 'lines.1.unit_price', 'unit_price.too_high'],
]);

function lineChanged(array $body, int $index, array $fields): array
{
    $body['lines'][$index] = [...$body['lines'][$index], ...$fields];

    return $body;
}

it('reads one purchase of the shop', function () {
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line]);
    TransactionsScenario::record($this, $body)->assertCreated();

    ContactsScenario::send($this, 'GET', 'transactions/'.$body['id'])
        ->assertOk()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.lines.0.id', $line['id']);
});

it('answers not found for an unknown purchase or one of another shop', function (string $whose) {
    ['token' => $otherToken] = ContactsScenario::merchantWithShop();
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line]);
    TransactionsScenario::record($this, $body)->assertCreated();
    $transactionId = match ($whose) {
        'other shop' => $body['id'],
        'unknown' => ContactsScenario::newId(),
        'not a uuid' => 'not-a-uuid',
    };

    ContactsScenario::send($this, 'GET', 'transactions/'.$transactionId, [], $otherToken)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'transaction.not_found']]);
})->with(['other shop', 'unknown', 'not a uuid']);

it('refuses recording and reading without a session or without a shop', function (string $method, string $uri) {
    $line = TransactionsScenario::line($this->tomatoes);
    $body = TransactionsScenario::purchase([$line]);
    $path = str_replace('{transaction}', $body['id'], $uri);
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, $method, $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, $method, $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
})->with([
    ['POST', 'transactions'],
    ['GET', 'transactions/{transaction}'],
]);
