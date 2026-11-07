<?php

use App\Modules\Catalog\database\factories\GlobalProductFactory;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
    $this->product = CatalogScenario::shopProduct($this->shop, ['name' => 'Harissa maison', 'sale_price' => 3000, 'purchase_price' => 2000]);
});

function patchProduct(object $test, $product, array $fields, ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();

    return $test->withToken($token ?? $test->token)->patchJson("/api/v1/products/{$product->uuid()}", $fields);
}

it('changes the name and the prices', function () {
    patchProduct($this, $this->product, ['name' => 'Harissa du Cap Bon', 'sale_price' => 3500, 'purchase_price' => 2200])
        ->assertOk()
        ->assertJsonPath('data.name', 'Harissa du Cap Bon')
        ->assertJsonPath('data.sale_price', 3500)
        ->assertJsonPath('data.purchase_price', 2200);

    $saved = $this->product->fresh();
    expect($saved->name)->toBe('Harissa du Cap Bon');
    expect($saved->sale_price)->toBe(3500);
    expect($saved->compared_name)->toBe('harissa du cap bon');
});

it('changes one price and leaves the rest', function () {
    patchProduct($this, $this->product, ['sale_price' => 3200])
        ->assertOk()
        ->assertJsonPath('data.name', 'Harissa maison')
        ->assertJsonPath('data.purchase_price', 2000);
});

it('clears the purchase price with null', function () {
    patchProduct($this, $this->product, ['purchase_price' => null])->assertOk()->assertJsonPath('data.purchase_price', null);

    expect($this->product->fresh()->purchase_price)->toBeNull();
});

it('needs at least one change', function () {
    patchProduct($this, $this->product, ['confirm_different' => true])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['fields' => ['fields.required']]]]);
});

it('refuses a change of id, barcode or catalog link', function (string $field) {
    patchProduct($this, $this->product, ['sale_price' => 3100, $field => 1])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', [$field => ["{$field}.prohibited"]]);
})->with(['id', 'barcode', 'catalog_product_id']);

it('checks prices as on creation', function () {
    patchProduct($this, $this->product, ['sale_price' => -1])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['sale_price' => ['sale_price.negative']]);
    patchProduct($this, $this->product, ['sale_price' => null])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['sale_price' => ['sale_price.required']]);
    patchProduct($this, $this->product, ['name' => '   '])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['name' => ['name.required']]);
});

it('refuses a rename to the name of another product', function () {
    $other = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates']);

    patchProduct($this, $this->product, ['name' => 'tomate', 'confirm_different' => true])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $other->uuid()]]);
});

it('allows its own name with another case', function () {
    patchProduct($this, $this->product, ['name' => 'HARISSA MAISON'])->assertOk()->assertJsonPath('data.name', 'HARISSA MAISON');
});

it('holds a close rename of a product with no link, then accepts it confirmed', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates cerises']);

    patchProduct($this, $this->product, ['name' => 'Tomates'])->assertStatus(409)->assertJsonPath('error.code', 'product.name_close');
    patchProduct($this, $this->product, ['name' => 'Tomates', 'confirm_different' => true])->assertOk();
});

it('holds a close rename of a product linked to an unbarcoded catalog product', function () {
    $tomatoes = CatalogScenario::tomatoes();
    $linked = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates', 'global_product_id' => $tomatoes->id]);
    CatalogScenario::shopProduct($this->shop, ['name' => 'Piment doux']);

    patchProduct($this, $linked, ['name' => 'Piments'])->assertStatus(409)->assertJsonPath('error.code', 'product.name_close');
});

it('never holds a rename of a product linked to a barcoded catalog product', function () {
    $biscuit = GlobalProduct::factory()->withBarcode(GlobalProductFactory::randomBarcode())->named('Biscuit', 'بسكويت', 'Biscuit')->create();
    $linked = CatalogScenario::shopProduct($this->shop, ['name' => 'Biscuit', 'global_product_id' => $biscuit->id]);
    CatalogScenario::shopProduct($this->shop, ['name' => 'Biscuit chocolat']);

    patchProduct($this, $linked, ['name' => 'Biscuit choco'])->assertOk();
});

it('refuses another shop product as not found and leaves it unchanged', function () {
    ['token' => $otherToken] = CatalogScenario::merchantWithShop();

    patchProduct($this, $this->product, ['sale_price' => 1], $otherToken)
        ->assertStatus(404)
        ->assertExactJson(['error' => ['code' => 'product.not_found']]);

    expect($this->product->fresh()->sale_price)->toBe(3000);
});

it('refuses a request with no session', function () {
    $this->patchJson('/api/v1/products/'.Str::uuid7(), ['sale_price' => 1])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    patchProduct($this, $this->product, ['sale_price' => 1], CatalogScenario::tokenOfMerchantWithoutShop())
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
