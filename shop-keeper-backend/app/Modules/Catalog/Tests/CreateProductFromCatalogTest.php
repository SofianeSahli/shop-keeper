<?php

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
    $this->tomatoes = CatalogScenario::tomatoes();
});

it('adds a suggested catalog product with the catalog name in the language sent', function (string $language, string $name) {
    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'language' => $language])
        ->assertCreated()
        ->assertJsonPath('data.name', $name)
        ->assertJsonPath('data.catalog_product.id', $this->tomatoes->id);

    expect(ShopProduct::query()->sole()->global_product_id)->toBe($this->tomatoes->id);
})->with([['fr', 'Tomate'], ['ar', 'طماطم'], ['en', 'Tomato']]);

it('keeps an own name and needs no language then', function () {
    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'name' => 'Tomates du marché'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tomates du marché');
});

it('needs a language when no name is sent', function () {
    postProduct($this, ['catalog_product_id' => $this->tomatoes->id])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['language' => ['language.required']]);
});

it('refuses an unknown language', function () {
    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'language' => 'de'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['language' => ['language.invalid']]);
});

it('refuses a missing or waiting catalog product', function (string $case) {
    $catalogProductId = match ($case) {
        'missing' => 999_999,
        'pending' => GlobalProduct::factory()->pending()->create()->id,
        'text' => 'abc',
    };

    postProduct($this, ['catalog_product_id' => $catalogProductId, 'name' => 'Biscuit'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['catalog_product_id' => ['catalog_product_id.unknown']]);
})->with(['missing', 'pending', 'text']);

it('refuses the same catalog product twice', function () {
    $existing = CatalogScenario::shopProduct($this->shop, ['global_product_id' => $this->tomatoes->id, 'name' => 'Tomates rondes']);

    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'name' => 'Autres tomates'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.already_in_shop', 'existing_product_id' => $existing->uuid()]]);
});

it('refuses a name identical to another product of the shop', function () {
    $existing = CatalogScenario::shopProduct($this->shop, ['name' => 'tomate']);

    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'language' => 'fr'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $existing->uuid()]]);
});

it('never holds a product added from the catalog for a close name', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates cerises']);

    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'language' => 'fr'])->assertCreated();
});
