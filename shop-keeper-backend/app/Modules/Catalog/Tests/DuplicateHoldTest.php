<?php

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
    $this->tomatoes = CatalogScenario::tomatoes();
    $this->existing = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates', 'global_product_id' => $this->tomatoes->id, 'sale_price' => 1700]);
});

it('holds a close name, shows the closest matches and creates nothing', function () {
    postProduct($this, ['name' => 'Tomates cerises'])
        ->assertStatus(409)
        ->assertExactJson(['error' => [
            'code' => 'product.name_close',
            'matches' => [[
                'catalog_product' => [
                    'id' => $this->tomatoes->id,
                    'key' => 'products.tomato',
                    'status' => 'verified',
                    'barcode' => null,
                    'names' => ['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato'],
                ],
                'shop_product' => ['id' => $this->existing->uuid(), 'name' => 'Tomates', 'sale_price' => 1700],
            ]],
        ]]);

    expect(ShopProduct::query()->count())->toBe(1);
});

it('creates the product once the merchant confirms it is different', function () {
    postProduct($this, ['name' => 'Tomates cerises', 'confirm_different' => true])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tomates cerises');
});

it('refuses the same compared name even with the confirmation', function (string $name) {
    postProduct($this, ['name' => $name, 'confirm_different' => true])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $this->existing->uuid()]]);
})->with(['tomate', 'TOMATES', ' Tomates ', 'tomateS']);

it('refuses a catalog product the shop already has, by id or by barcode', function () {
    postProduct($this, ['catalog_product_id' => $this->tomatoes->id, 'name' => 'Autres', 'confirm_different' => true])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.already_in_shop', 'existing_product_id' => $this->existing->uuid()]]);
});

it('leaves one product when an identical creation wins the race', function () {
    $twinId = (string) Str::uuid7();
    ShopProduct::creating(function (ShopProduct $product) use ($twinId): void {
        if ($product->uuid() === $twinId) {
            return;
        }
        DB::table('shop_products')->insert([
            'id' => BinaryUuid::toBytes($twinId),
            'shop_id' => $product->shop_id,
            'name' => 'Pain rond',
            'compared_name' => 'pain rond',
            'identity_key' => 'painrond',
            'sale_price' => 300,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    postProduct($this, ['name' => 'Pain rond'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $twinId]]);
});

it('creates one product when the same request is sent twice', function () {
    $body = CatalogScenario::creation(['name' => 'Pain rond']);

    $this->withToken($this->token)->postJson('/api/v1/products', $body)->assertCreated();
    $this->withToken($this->token)->postJson('/api/v1/products', $body)->assertOk()->assertJsonPath('data.id', $body['id']);

    expect(ShopProduct::query()->ofShop($this->shop->id)->count())->toBe(2);
});
