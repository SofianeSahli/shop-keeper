<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Contracts\PurchaseLineSources;
use App\Modules\Inventory\Support\PurchaseLineSource;
use App\Modules\Inventory\Tests\Support\InventoryScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = InventoryScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->tomatoes = InventoryScenario::product($this->shop);
});

function recordTomatoHistory(object $test): array
{
    $morning = CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC');
    $test->travelTo($morning);
    $openingLine = InventoryScenario::openingLine($test->tomatoes, 10);
    $opening = InventoryScenario::adjustment([$openingLine], ['adjustment_date' => '2026-11-19']);
    InventoryScenario::openStock($test, $opening)->assertCreated();
    $purchaseLine = TransactionsScenario::line($test->tomatoes, 5);
    $purchase = TransactionsScenario::purchase([$purchaseLine], ['transaction_date' => '2026-11-20']);
    TransactionsScenario::record($test, $purchase)->assertCreated();
    $lossLine = InventoryScenario::lossLine($test->tomatoes, 2, 'broken', 'fell from the shelf');
    $loss = InventoryScenario::adjustment([$lossLine]);
    InventoryScenario::lose($test, $loss)->assertCreated();
    $countLine = InventoryScenario::countLine($test->tomatoes, 12, 13);
    $count = InventoryScenario::adjustment([$countLine]);
    InventoryScenario::count($test, $count)->assertCreated();
    $lateEvening = CarbonImmutable::parse('2026-11-21 23:30:00', 'UTC');
    $test->travelTo($lateEvening);
    $purchaseUri = 'transactions/'.$purchase['id'];
    PaymentsScenario::cancel($test, $purchaseUri, ['reason' => 'wrong supplier'])->assertCreated();

    return ['opening' => $opening, 'purchase' => $purchase, 'loss' => $loss, 'count' => $count];
}

it('lists the movements newest first with the stock after each one and their details', function () {
    $records = recordTomatoHistory($this);
    $recorders = array_fill(0, 5, $this->account);

    InventoryScenario::history($this, $this->tomatoes)
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.*.kind', ['purchase_cancellation', 'count', 'loss', 'purchase', 'opening_stock'])
        ->assertJsonPath('data.*.quantity', [-5, -1, -2, 5, 10])
        ->assertJsonPath('data.*.stock_after', [7, 12, 13, 15, 10])
        ->assertJsonPath('data.*.date', ['2026-11-22', '2026-11-20', '2026-11-20', '2026-11-20', '2026-11-19'])
        ->assertJsonPath('data.*.recorded_by', $recorders)
        ->assertJsonPath('data.*.transaction_id', [$records['purchase']['id'], null, null, $records['purchase']['id'], null])
        ->assertJsonPath('data.*.adjustment_id', [null, $records['count']['id'], $records['loss']['id'], null, $records['opening']['id']])
        ->assertJsonPath('data.1.counted_quantity', 12)
        ->assertJsonPath('data.1.shown_quantity', 13)
        ->assertJsonPath('data.2.reason', 'broken')
        ->assertJsonPath('data.2.note', 'fell from the shelf')
        ->assertJsonPath('data.0.recorded_at', '2026-11-21T23:30:00Z')
        ->assertJsonPath('data.0.reason', null);

    $stock = InventoryScenario::stockOf($this->tomatoes);
    expect($stock)->toBe(7);
});

it('gives the right stock after every entry on every page', function () {
    recordTomatoHistory($this);
    $stockAfterEach = [];
    $cursor = null;

    do {
        $query = $cursor === null ? ['per_page' => 2] : ['per_page' => 2, 'cursor' => $cursor];
        $page = InventoryScenario::history($this, $this->tomatoes, $query)->assertOk();
        $pageStocks = $page->json('data.*.stock_after');
        $stockAfterEach = [...$stockAfterEach, ...$pageStocks];
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null);

    expect($stockAfterEach)->toBe([7, 12, 13, 15, 10]);
});

it('takes the purchase details from the transactions module', function () {
    $purchaseLine = TransactionsScenario::line($this->tomatoes, 5);
    $purchase = TransactionsScenario::purchase([$purchaseLine]);
    TransactionsScenario::record($this, $purchase)->assertCreated();
    $fakeTransactionId = InventoryScenario::newId();
    $fakeSources = new class($purchaseLine['id'], $fakeTransactionId) implements PurchaseLineSources
    {
        public function __construct(private string $lineId, private string $transactionId) {}

        public function describe(int $shopId, array $lineIds): array
        {
            $source = new PurchaseLineSource($this->transactionId, '2026-01-02', 99, null, null);

            return [$this->lineId => $source];
        }
    };
    $this->app->instance(PurchaseLineSources::class, $fakeSources);

    InventoryScenario::history($this, $this->tomatoes)
        ->assertOk()
        ->assertJsonPath('data.0.transaction_id', $fakeTransactionId)
        ->assertJsonPath('data.0.date', '2026-01-02')
        ->assertJsonPath('data.0.recorded_by', 99);
});

it('gives an empty page for a product with no movement', function () {
    InventoryScenario::history($this, $this->tomatoes)
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.next_cursor', null);
});

it('answers not found for the history of an unknown product or one of another shop', function (string $whose) {
    ['shop' => $otherShop] = InventoryScenario::merchantWithShop();
    $foreignProduct = InventoryScenario::product($otherShop);
    $productId = match ($whose) {
        'other shop' => $foreignProduct->uuid(),
        'unknown' => InventoryScenario::newId(),
        'not a uuid' => 'not-a-uuid',
    };

    ContactsScenario::send($this, 'GET', 'products/'.$productId.'/stock-movements')
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'product.not_found']]);
})->with(['other shop', 'unknown', 'not a uuid']);

it('refuses a wrong page size or cursor', function (array $query, string $field, string $code) {
    $response = InventoryScenario::history($this, $this->tomatoes, $query);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with([
    'page size of 0' => [['per_page' => 0], 'per_page', 'per_page.invalid'],
    'page size too high' => [['per_page' => 101], 'per_page', 'per_page.invalid'],
    'page size not a number' => [['per_page' => 'all'], 'per_page', 'per_page.invalid'],
    'cursor not text' => [['cursor' => ['x']], 'cursor', 'cursor.invalid'],
]);

it('reads a page with a number of queries that does not grow with the page size', function () {
    recordTomatoHistory($this);
    DB::enableQueryLog();
    InventoryScenario::history($this, $this->tomatoes, ['per_page' => 1])->assertOk();
    $smallPageLog = DB::getQueryLog();
    $smallPageQueries = count($smallPageLog);
    DB::flushQueryLog();

    InventoryScenario::history($this, $this->tomatoes, ['per_page' => 5])->assertOk();
    $largePageLog = DB::getQueryLog();
    $largePageQueries = count($largePageLog);

    expect($largePageQueries)->toBeLessThanOrEqual($smallPageQueries + 2);
});

it('refuses the history without a session or without a shop', function () {
    $productId = $this->tomatoes->uuid();
    $uri = 'products/'.$productId.'/stock-movements';
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, 'GET', $uri)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, 'GET', $uri, [], $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
