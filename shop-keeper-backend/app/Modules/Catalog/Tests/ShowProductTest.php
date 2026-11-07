<?php

use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Support\Str;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
});

it('reads one product of the shop', function () {
    $tomatoes = CatalogScenario::tomatoes();
    $product = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates', 'global_product_id' => $tomatoes->id, 'sale_price' => 1800, 'purchase_price' => 1100]);

    $this->withToken($this->token)->getJson("/api/v1/products/{$product->uuid()}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $product->uuid(),
            'name' => 'Tomates',
            'sale_price' => 1800,
            'purchase_price' => 1100,
            'catalog_product' => [
                'id' => $tomatoes->id,
                'key' => 'products.tomato',
                'status' => 'verified',
                'barcode' => null,
                'names' => ['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato'],
            ],
            'created_at' => $product->created_at->toIso8601ZuluString(),
            'updated_at' => $product->updated_at->toIso8601ZuluString(),
        ]]);
});

it('answers another shop product, an unknown id and a non-id the same way', function (string $case) {
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    $foreign = CatalogScenario::shopProduct($otherShop, ['name' => 'Foreign secret']);
    $productId = match ($case) {
        'another shop' => $foreign->uuid(),
        'unknown' => (string) Str::uuid7(),
        'not an id' => 'not-a-uuid',
    };

    $response = $this->withToken($this->token)->getJson('/api/v1/products/'.$productId)
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'product.not_found']]);

    expect($response->getContent())->not->toContain('Foreign secret');
})->with(['another shop', 'unknown', 'not an id']);

it('refuses a request with no session', function () {
    $this->getJson('/api/v1/products/'.Str::uuid7())->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    $this->withToken(CatalogScenario::tokenOfMerchantWithoutShop())->getJson('/api/v1/products/'.Str::uuid7())
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
