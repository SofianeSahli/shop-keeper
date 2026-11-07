<?php

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
});

it('lists the shop products in name order, with catalog names when linked', function () {
    $tomatoes = CatalogScenario::tomatoes();
    $pending = GlobalProduct::factory()->pending('Typed elsewhere')->create();
    CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates', 'global_product_id' => $tomatoes->id]);
    CatalogScenario::shopProduct($this->shop, ['name' => 'Biscuit', 'global_product_id' => $pending->id]);
    CatalogScenario::shopProduct($this->shop, ['name' => 'Eau minérale']);
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['name' => 'Autre boutique']);

    $response = $this->withToken($this->token)->getJson('/api/v1/products')->assertOk();

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Biscuit', 'Eau minérale', 'Tomates']);
    expect($response->json('data.0.catalog_product.status'))->toBe('pending');
    expect($response->json('data.0.catalog_product.names'))->toBeNull();
    expect($response->json('data.2.catalog_product.names'))->toBe(['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato']);
    expect($response->json('meta.per_page'))->toBe(50);
    expect($response->getContent())->not->toContain('Typed elsewhere');
});

it('pages with a cursor and no overlap', function () {
    foreach (range(1, 5) as $number) {
        CatalogScenario::shopProduct($this->shop, ['name' => "Produit {$number}"]);
    }

    $first = $this->withToken($this->token)->getJson('/api/v1/products?per_page=2')->assertOk();
    $second = $this->withToken($this->token)->getJson('/api/v1/products?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertOk();
    $third = $this->withToken($this->token)->getJson('/api/v1/products?per_page=2&cursor='.$second->json('meta.next_cursor'))->assertOk();

    expect(collect([$first, $second, $third])->flatMap(fn ($page) => collect($page->json('data'))->pluck('name'))->all())
        ->toBe(['Produit 1', 'Produit 2', 'Produit 3', 'Produit 4', 'Produit 5']);
    expect($third->json('meta.next_cursor'))->toBeNull();
});

it('refuses a page size out of range', function (string $perPage) {
    $this->withToken($this->token)->getJson("/api/v1/products?per_page={$perPage}")
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['per_page' => ['per_page.invalid']]]]);
})->with(['0', '101', 'ten']);

it('refuses a request with no session', function () {
    $this->getJson('/api/v1/products')->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    $this->withToken(CatalogScenario::tokenOfMerchantWithoutShop())->getJson('/api/v1/products')
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
