<?php

use App\Modules\Catalog\database\factories\GlobalProductFactory;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
});

it('describes a checked catalog product with its key and three names', function () {
    $barcode = GlobalProductFactory::randomBarcode();
    $tomatoes = CatalogScenario::tomatoes($barcode);

    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'barcode' => $barcode,
            'status' => 'verified',
            'catalog_product' => [
                'id' => $tomatoes->id,
                'key' => 'products.tomato',
                'status' => 'verified',
                'barcode' => $barcode,
                'names' => ['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato'],
            ],
            'shop_product' => null,
        ]]);
});

it('says when the shop already has the product', function () {
    $barcode = GlobalProductFactory::randomBarcode();
    $tomatoes = CatalogScenario::tomatoes($barcode);
    $shopProduct = CatalogScenario::shopProduct($this->shop, ['global_product_id' => $tomatoes->id, 'name' => 'Tomates']);

    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertOk()
        ->assertJsonPath('data.shop_product', [
            'id' => $shopProduct->uuid(),
            'name' => 'Tomates',
            'sale_price' => $shopProduct->sale_price,
        ]);
});

it('shows a product waiting to be checked with no name', function () {
    $pending = GlobalProduct::factory()->pending('Secret typed name')->create();

    $response = $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$pending->barcode}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.catalog_product.names', null)
        ->assertJsonPath('data.catalog_product.id', $pending->id);

    expect($response->getContent())->not->toContain('Secret typed name');
});

it('answers unknown for a barcode the catalog does not know', function () {
    $barcode = GlobalProductFactory::randomBarcode();

    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'barcode' => $barcode,
            'status' => 'unknown',
            'catalog_product' => null,
            'shop_product' => null,
        ]]);
});

it('finds the same product with spaces or as a UPC-A', function () {
    $tomatoes = CatalogScenario::tomatoes('0036000291452');

    $this->withToken($this->token)->getJson('/api/v1/catalog/barcodes/036000291452')
        ->assertOk()
        ->assertJsonPath('data.barcode', '0036000291452')
        ->assertJsonPath('data.catalog_product.id', $tomatoes->id);

    $this->withToken($this->token)->getJson('/api/v1/catalog/barcodes/'.rawurlencode('0 036000 291452'))
        ->assertOk()
        ->assertJsonPath('data.catalog_product.id', $tomatoes->id);
});

it('refuses a wrong check digit and an in-store code and creates nothing', function (string $barcode, string $fieldCode) {
    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['barcode' => [$fieldCode]]]]);

    expect(GlobalProduct::query()->count())->toBe(0);
})->with([
    'wrong check digit' => ['6191234567890', 'barcode.invalid'],
    'unsupported length' => ['12345', 'barcode.invalid'],
    'in-store code' => ['2001234567893', 'barcode.in_store'],
]);

it('never shows another shop product as the shop product', function () {
    $barcode = GlobalProductFactory::randomBarcode();
    $tomatoes = CatalogScenario::tomatoes($barcode);
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['global_product_id' => $tomatoes->id]);

    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertOk()
        ->assertJsonPath('data.shop_product', null);
});

it('refuses a request with no session', function () {
    $this->getJson('/api/v1/catalog/barcodes/6191234567897')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    $this->withToken(CatalogScenario::tokenOfMerchantWithoutShop())->getJson('/api/v1/catalog/barcodes/6191234567897')
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});

it('limits lookups per account', function () {
    $barcode = GlobalProductFactory::randomBarcode();

    foreach (range(1, 120) as $attempt) {
        $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")->assertOk();
    }

    $this->withToken($this->token)->getJson("/api/v1/catalog/barcodes/{$barcode}")
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
