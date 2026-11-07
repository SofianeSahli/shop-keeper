<?php

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
});

it('creates a product of the shop own with no catalog link', function () {
    postProduct($this, ['name' => 'Harissa maison', 'purchase_price' => 2500])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Harissa maison')
        ->assertJsonPath('data.purchase_price', 2500)
        ->assertJsonPath('data.catalog_product', null);

    expect(ShopProduct::query()->sole()->global_product_id)->toBeNull();
    expect(GlobalProduct::query()->count())->toBe(0);
});

it('needs a name', function (array $fields) {
    postProduct($this, $fields)
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['name' => ['name.required']]);
})->with([
    'missing' => [[]],
    'only spaces' => [['name' => '    ']],
]);

it('refuses a name longer than 120 characters', function () {
    postProduct($this, ['name' => str_repeat('é', 121)])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['name' => ['name.too_long']]);
});

it('keeps Arabic and accented names exactly as typed', function (string $name) {
    postProduct($this, ['name' => $name])->assertCreated()->assertJsonPath('data.name', $name);
})->with(['هريسة دار', 'Crème fraîche épaisse']);

it('removes spaces at the ends of the name', function () {
    postProduct($this, ['name' => '  Harissa maison  '])->assertCreated()->assertJsonPath('data.name', 'Harissa maison');
});

it('refuses a name identical to another product once compared', function () {
    $existing = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates']);

    postProduct($this, ['name' => 'tomate'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $existing->uuid()]]);
});

it('accepts the same name as a product of another shop', function () {
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['name' => 'Harissa maison']);

    postProduct($this, ['name' => 'Harissa maison'])->assertCreated();
});
