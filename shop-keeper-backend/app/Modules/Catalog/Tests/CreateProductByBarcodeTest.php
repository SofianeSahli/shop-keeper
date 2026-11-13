<?php

use App\Modules\Catalog\Actions\FindOrCreatePendingProduct;
use App\Modules\Catalog\database\factories\GlobalProductFactory;
use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
    $this->barcode = GlobalProductFactory::randomBarcode();
});

it('adds a checked catalog product with the catalog name in the language sent', function () {
    $tomatoes = CatalogScenario::tomatoes($this->barcode);
    $body = CatalogScenario::creation(['barcode' => $this->barcode, 'language' => 'ar', 'purchase_price' => 1200]);

    $response = $this->withToken($this->token)->postJson('/api/v1/products', $body)->assertCreated();

    $product = ShopProduct::query()->sole();
    $response->assertExactJson(['data' => [
        'id' => $body['id'],
        'name' => 'طماطم',
        'sale_price' => CatalogScenario::SALE_PRICE,
        'purchase_price' => 1200,
        'catalog_product' => [
            'id' => $tomatoes->id,
            'key' => 'products.tomato',
            'status' => 'verified',
            'barcode' => $this->barcode,
            'names' => ['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato'],
        ],
        'created_at' => $product->created_at->toIso8601ZuluString(),
        'updated_at' => $product->updated_at->toIso8601ZuluString(),
    ]]);
    expect($product->shop_id)->toBe($this->shop->id);
    expect($product->global_product_id)->toBe($tomatoes->id);
});

it('keeps the name the merchant sends and has no purchase price by default', function () {
    CatalogScenario::tomatoes($this->barcode);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Tomates du Cap Bon'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tomates du Cap Bon')
        ->assertJsonPath('data.purchase_price', null);
});

it('asks for a language when a checked product is added with no name', function () {
    CatalogScenario::tomatoes($this->barcode);

    postProduct($this, ['barcode' => $this->barcode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['language' => ['language.required']]]]);
});

it('adds an unknown barcode to the catalog as waiting to be checked', function () {
    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Biscuit choco', 'language' => 'fr'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Biscuit choco')
        ->assertJsonPath('data.catalog_product.status', 'pending')
        ->assertJsonPath('data.catalog_product.names', null)
        ->assertJsonPath('data.catalog_product.key', "products.barcode_{$this->barcode}");

    $pending = GlobalProduct::query()->sole();
    expect($pending->status)->toBe(CatalogStatus::Pending);
    expect($pending->pending_name)->toBe('Biscuit choco');
    expect($pending->pending_language)->toBe('fr');
    expect(ShopProduct::query()->sole()->global_product_id)->toBe($pending->id);
});

it('needs a name and a language for an unknown barcode', function () {
    postProduct($this, ['barcode' => $this->barcode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'name' => ['name.required'],
            'language' => ['language.required'],
        ]]]);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Biscuit'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['language' => ['language.required']]);

    expect(GlobalProduct::query()->count())->toBe(0);
});

it('links a second shop to the same pending product, with a name of its own', function () {
    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Biscuit choco', 'language' => 'fr'])->assertCreated();
    ['token' => $secondToken] = CatalogScenario::merchantWithShop();

    postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr'], $secondToken)
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['name' => ['name.required']]);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Choco', 'language' => 'ar'], $secondToken)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Choco');

    expect(GlobalProduct::query()->count())->toBe(1);
    expect(ShopProduct::query()->pluck('global_product_id')->unique()->all())->toBe([GlobalProduct::query()->sole()->id]);
    expect(GlobalProduct::query()->sole()->pending_name)->toBe('Biscuit choco');
});

it('refuses the same barcode twice in a shop and points to the existing product', function () {
    $tomatoes = CatalogScenario::tomatoes($this->barcode);
    $existing = CatalogScenario::shopProduct($this->shop, ['global_product_id' => $tomatoes->id]);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Autres tomates'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.already_in_shop', 'existing_product_id' => $existing->uuid()]]);
});

it('refuses a name identical to another product of the shop', function () {
    CatalogScenario::tomatoes($this->barcode);
    $existing = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates']);

    postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.name_taken', 'existing_product_id' => $existing->uuid()]]);
});

it('never holds a product added with a barcode for a close name', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Biscuit choco']);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'Biscuit chocolat', 'language' => 'fr'])
        ->assertCreated();
});

it('refuses a barcode and a catalog product id together', function () {
    $tomatoes = CatalogScenario::tomatoes();

    postProduct($this, ['barcode' => $this->barcode, 'catalog_product_id' => $tomatoes->id, 'name' => 'Tomates'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['catalog_product_id' => ['catalog_product_id.prohibited']]);
});

it('accepts a zero price and refuses negative, text, fractional and too high prices', function () {
    CatalogScenario::tomatoes($this->barcode);

    foreach ([
        [-1, 'sale_price.negative'],
        ['1500', 'sale_price.integer'],
        [1.5, 'sale_price.integer'],
        [1_000_000_000, 'sale_price.too_high'],
    ] as [$price, $fieldCode]) {
        postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr', 'sale_price' => $price])
            ->assertStatus(422)
            ->assertJsonPath('error.fields', ['sale_price' => [$fieldCode]]);
    }

    postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr', 'purchase_price' => -5])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['purchase_price' => ['purchase_price.negative']]);

    postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr', 'sale_price' => 0])
        ->assertCreated()
        ->assertJsonPath('data.sale_price', 0);
});

it('needs a sale price and a valid id', function () {
    $this->withToken($this->token)->postJson('/api/v1/products', ['id' => 'not-a-uuid'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields', ['id' => ['id.invalid'], 'sale_price' => ['sale_price.required']]);
});

it('returns the existing product when the same id is sent again', function () {
    $body = CatalogScenario::creation(['barcode' => $this->barcode, 'name' => 'Biscuit choco', 'language' => 'fr']);

    $first = $this->withToken($this->token)->postJson('/api/v1/products', $body)->assertCreated();
    $this->withToken($this->token)->postJson('/api/v1/products', $body)
        ->assertOk()
        ->assertExactJson($first->json());

    expect(ShopProduct::query()->count())->toBe(1);
});

it('refuses an id held by another shop without revealing it', function () {
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    $foreign = CatalogScenario::shopProduct($otherShop, ['name' => 'Foreign secret']);

    $response = postProduct($this, ['id' => $foreign->uuid(), 'name' => 'Mine'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['id' => ['id.taken']]]]);

    expect($response->getContent())->not->toContain('Foreign secret');
});

it('leaves no pending catalog product when the creation is refused', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Biscuit choco']);

    postProduct($this, ['barcode' => $this->barcode, 'name' => 'biscuits chocos', 'language' => 'fr'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'product.name_taken');

    expect(GlobalProduct::query()->count())->toBe(0);
});

it('links to the pending product another shop created at the same moment', function () {
    $racer = GlobalProduct::factory()->pending()->state([
        'barcode' => $this->barcode,
        'translation_key' => GlobalProduct::pendingKeyFor($this->barcode),
    ])->create();

    $catalogProduct = app(FindOrCreatePendingProduct::class)->handle(Barcode::parse($this->barcode), 'Late name', CatalogLanguage::French);

    expect($catalogProduct->id)->toBe($racer->id);
    expect(GlobalProduct::query()->count())->toBe(1);
    expect($catalogProduct->pending_name)->toBe($racer->pending_name);
});

it('answers like the first request when an identical creation wins the race', function () {
    $tomatoes = CatalogScenario::tomatoes($this->barcode);
    $twinId = (string) Str::uuid7();
    ShopProduct::creating(function (ShopProduct $product) use ($tomatoes, $twinId): void {
        if (DB::table('shop_products')->where('id', BinaryUuid::toBytes($twinId))->exists()) {
            return;
        }
        DB::table('shop_products')->insert([
            'id' => BinaryUuid::toBytes($twinId),
            'shop_id' => $product->shop_id,
            'global_product_id' => $tomatoes->id,
            'name' => 'Twin',
            'compared_name' => 'twin',
            'identity_key' => 'twin',
            'sale_price' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    postProduct($this, ['barcode' => $this->barcode, 'language' => 'fr'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'product.already_in_shop', 'existing_product_id' => $twinId]]);
});

it('refuses a request with no session', function () {
    $this->postJson('/api/v1/products', CatalogScenario::creation(['name' => 'Pain']))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    postProduct($this, ['name' => 'Pain'], CatalogScenario::tokenOfMerchantWithoutShop())
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
