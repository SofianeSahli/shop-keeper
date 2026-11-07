<?php

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = CatalogScenario::merchantWithShop();
});

function suggestionsFor(object $test, string $search, ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();

    return $test->withToken($token ?? $test->token)->getJson('/api/v1/products/suggestions?q='.rawurlencode($search));
}

function suggestedCatalogIds(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('catalog_product.id')->filter()->values()->all();
}

it('finds the tomatoes in French, Arabic and Latin-script Tunisian', function (string $search) {
    $tomatoes = CatalogScenario::tomatoes();

    expect(suggestedCatalogIds(suggestionsFor($this, $search)->assertOk()))->toContain($tomatoes->id);
})->with(['tom', 'Tomates', 'TOMATE', 'طما', 'طماطة', 'tmat', 'Tomato']);

it('ignores accents and Arabic letter variants', function () {
    $semolina = GlobalProduct::factory()->named('Semoule fine', 'سميد رقيق', 'Fine semolina')->create();
    $rice = GlobalProduct::factory()->named('Riz', 'أرز', 'Rice')->create();

    expect(suggestedCatalogIds(suggestionsFor($this, 'sémou')))->toContain($semolina->id);
    expect(suggestedCatalogIds(suggestionsFor($this, 'ارز')))->toContain($rice->id);
    expect(suggestedCatalogIds(suggestionsFor($this, 'إرز')))->toContain($rice->id);
});

it('gives the suggestion shape, with the shop product when the shop has it', function () {
    $tomatoes = CatalogScenario::tomatoes();
    $mine = CatalogScenario::shopProduct($this->shop, ['global_product_id' => $tomatoes->id, 'name' => 'Tomates rondes', 'sale_price' => 1900]);
    $own = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates séchées maison', 'sale_price' => 9000]);

    $data = suggestionsFor($this, 'tomat')->assertOk()->json('data');

    expect($data)->toHaveCount(2);
    expect($data)->toContain([
        'catalog_product' => [
            'id' => $tomatoes->id,
            'key' => 'products.tomato',
            'status' => 'verified',
            'barcode' => null,
            'names' => ['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato'],
        ],
        'shop_product' => ['id' => $mine->uuid(), 'name' => 'Tomates rondes', 'sale_price' => 1900],
    ]);
    expect($data)->toContain([
        'catalog_product' => null,
        'shop_product' => ['id' => $own->uuid(), 'name' => 'Tomates séchées maison', 'sale_price' => 9000],
    ]);
});

it('ranks exact names first, then name starts, word starts and the rest', function () {
    $contains = GlobalProduct::factory()->named('Pastomate', 'باستوماط', 'Pastomate')->create();
    $wordStart = GlobalProduct::factory()->named('Sauce tomate', 'صلصة طماطم', 'Tomato sauce')->create();
    $start = GlobalProduct::factory()->named('Tomate cerise', 'طماطم كرزية', 'Cherry tomato')->create();
    $exact = CatalogScenario::tomatoes();

    expect(suggestedCatalogIds(suggestionsFor($this, 'tomate')))->toBe([$exact->id, $start->id, $wordStart->id, $contains->id]);
});

it('puts the shop products first on a tie', function () {
    GlobalProduct::factory()->named('Pain rond', 'خبز مدور', 'Round bread')->create();
    $own = CatalogScenario::shopProduct($this->shop, ['name' => 'Pain maison']);

    expect(suggestionsFor($this, 'pain')->json('data.0.shop_product.id'))->toBe($own->uuid());
});

it('returns at most 10 suggestions', function () {
    foreach (range(1, 12) as $number) {
        GlobalProduct::factory()->named("Pâtes {$number}", "معكرونة {$number}", "Pasta {$number}")->create();
    }

    expect(suggestionsFor($this, 'pat')->json('data'))->toHaveCount(10);
});

it('never suggests a product waiting to be checked', function () {
    GlobalProduct::factory()->pending('Tomates secrètes')->create();

    $response = suggestionsFor($this, 'secrète')->assertOk()->assertExactJson(['data' => []]);

    expect($response->getContent())->not->toContain('secr');
});

it('never suggests another shop product', function () {
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['name' => 'Harissa du voisin']);

    suggestionsFor($this, 'harissa')->assertOk()->assertExactJson(['data' => []]);
});

it('refuses a search too short, too long or missing', function (string $query, string $fieldCode) {
    $this->withToken($this->token)->getJson('/api/v1/products/suggestions'.$query)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['q' => [$fieldCode]]]]);
})->with([
    'one character' => ['?q=t', 'q.too_short'],
    'only spaces' => ['?q=%20%20%20', 'q.required'],
    'missing' => ['', 'q.required'],
    'too long' => ['?q='.str_repeat('a', 121), 'q.too_long'],
]);

it('refuses a request with no session', function () {
    $this->getJson('/api/v1/products/suggestions?q=tom')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('refuses a merchant with no shop', function () {
    suggestionsFor($this, 'tom', CatalogScenario::tokenOfMerchantWithoutShop())
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});

it('shares its limit with barcode lookups', function () {
    foreach (range(1, 120) as $attempt) {
        $this->withToken($this->token)->getJson('/api/v1/catalog/barcodes/6191234567897')->assertOk();
    }

    suggestionsFor($this, 'tom')->assertStatus(429)->assertJsonPath('error.code', 'request.throttled');
});
