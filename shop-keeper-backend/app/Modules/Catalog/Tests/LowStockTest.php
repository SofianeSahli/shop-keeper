<?php

use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockMovement;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
    $this->product = CatalogScenario::shopProduct($this->shop, ['name' => 'Harissa maison', 'sale_price' => 3000]);
    $this->productUri = 'products/'.$this->product->uuid();
});

function lowStockIds(object $test, array $query = []): array
{
    $queryString = http_build_query($query);
    $response = ContactsScenario::send($test, 'GET', 'products/low-stock?'.$queryString)->assertOk();

    return $response->json('data.*.id');
}

it('sets, changes and clears the low-stock level without touching the rest', function () {
    ContactsScenario::send($this, 'PATCH', $this->productUri, ['low_stock_level' => 5])
        ->assertOk()
        ->assertJsonPath('data.low_stock_level', 5)
        ->assertJsonPath('data.name', 'Harissa maison')
        ->assertJsonPath('data.sale_price', 3000);
    ContactsScenario::send($this, 'PATCH', $this->productUri, ['low_stock_level' => 0])
        ->assertOk()
        ->assertJsonPath('data.low_stock_level', 0);
    ContactsScenario::send($this, 'PATCH', $this->productUri, ['low_stock_level' => null])
        ->assertOk()
        ->assertJsonPath('data.low_stock_level', null);

    $savedProduct = $this->product->fresh();
    $movementCount = StockMovement::query()->count();
    expect([$savedProduct->low_stock_level, $savedProduct->stock_quantity, $movementCount])->toBe([null, 0, 0]);
});

it('sets the level of a product whose name is close to another one without holding it', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Harissa maisonn']);

    ContactsScenario::send($this, 'PATCH', $this->productUri, ['low_stock_level' => 3])
        ->assertOk()
        ->assertJsonPath('data.low_stock_level', 3);
});

it('answers the low-stock level on a product', function () {
    ContactsScenario::send($this, 'GET', $this->productUri)
        ->assertOk()
        ->assertJsonPath('data.low_stock_level', null);
});

it('refuses a wrong low-stock level', function (mixed $level, string $code) {
    $response = ContactsScenario::send($this, 'PATCH', $this->productUri, ['low_stock_level' => $level]);

    ContactsScenario::assertFieldError($response, 'low_stock_level', $code);
})->with([
    'not whole' => [1.5, 'low_stock_level.integer'],
    'as text' => ['5', 'low_stock_level.integer'],
    'negative' => [-1, 'low_stock_level.negative'],
    'too high' => [1_000_000, 'low_stock_level.too_high'],
]);

it('lists exactly the products at or below their level, lowest stock first', function () {
    $atLevel = CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 5, 'low_stock_level' => 5]);
    $belowZero = CatalogScenario::shopProduct($this->shop, ['stock_quantity' => -2, 'low_stock_level' => 3]);
    $below = CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 1, 'low_stock_level' => 4]);
    CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 6, 'low_stock_level' => 5]);
    CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 0, 'low_stock_level' => null]);
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['stock_quantity' => 0, 'low_stock_level' => 10]);

    $belowZeroId = $belowZero->uuid();
    $belowId = $below->uuid();
    $atLevelId = $atLevel->uuid();

    $listedIds = lowStockIds($this);

    expect($listedIds)->toBe([$belowZeroId, $belowId, $atLevelId]);
});

it('lists only stock of 0 or below for a level of 0', function () {
    $empty = CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 0, 'low_stock_level' => 0]);
    CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 1, 'low_stock_level' => 0]);

    $emptyId = $empty->uuid();

    $listedIds = lowStockIds($this);

    expect($listedIds)->toBe([$emptyId]);
});

it('pages the low-stock list with a cursor', function () {
    $products = [];

    for ($stock = 0; $stock < 5; $stock++) {
        $products[] = CatalogScenario::shopProduct($this->shop, ['stock_quantity' => $stock, 'low_stock_level' => 10]);
    }

    CatalogScenario::shopProduct($this->shop, ['stock_quantity' => 2, 'low_stock_level' => 10]);
    $listedIds = [];
    $cursor = null;

    do {
        $query = $cursor === null ? ['per_page' => 2] : ['per_page' => 2, 'cursor' => $cursor];
        $queryString = http_build_query($query);
        $page = ContactsScenario::send($this, 'GET', 'products/low-stock?'.$queryString)->assertOk();
        $pageIds = $page->json('data.*.id');
        $listedIds = [...$listedIds, ...$pageIds];
        $cursor = $page->json('meta.next_cursor');
    } while ($cursor !== null);

    $uniqueIds = array_unique($listedIds);
    $lowestId = $products[0]->uuid();
    $highestId = $products[4]->uuid();
    expect($listedIds)->toHaveCount(6);
    expect($uniqueIds)->toHaveCount(6);
    expect($listedIds[0])->toBe($lowestId);
    expect($listedIds[5])->toBe($highestId);
});

it('refuses a wrong page size', function () {
    $response = ContactsScenario::send($this, 'GET', 'products/low-stock?per_page=0');

    ContactsScenario::assertFieldError($response, 'per_page', 'per_page.invalid');
});

it('refuses the low-stock list and the level without a session or without a shop', function (string $method, string $uri) {
    $path = str_replace('{product}', $this->product->uuid(), $uri);
    $body = ['low_stock_level' => 5];
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, $method, $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, $method, $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
})->with([
    ['GET', 'products/low-stock'],
    ['PATCH', 'products/{product}'],
]);
