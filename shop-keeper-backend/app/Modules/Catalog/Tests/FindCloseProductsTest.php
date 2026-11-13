<?php

use App\Modules\Catalog\Actions\FindCloseProducts;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Support\ProductMatch;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

beforeEach(function () {
    ['shop' => $this->shop] = CatalogScenario::merchantWithShop();
});

function closeNamesOf(int $shopId, string $name, $renamedProduct = null): array
{
    return array_map(
        fn (ProductMatch $match): string => $match->displayName(),
        app(FindCloseProducts::class)->handle($shopId, $name, $renamedProduct),
    );
}

it('finds a name that contains the other', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates cerises']);

    expect(closeNamesOf($this->shop->id, 'Cerise'))->toBe(['Tomates cerises']);
    expect(closeNamesOf($this->shop->id, 'Tomates cerises bio'))->toBe(['Tomates cerises']);
});

it('finds a name sharing a word of at least 3 letters', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Lait demi écrémé']);

    expect(closeNamesOf($this->shop->id, 'Lait entier'))->toBe(['Lait demi écrémé']);
});

it('finds a name differing by at most 2 letters, in French and Arabic', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Carottes']);
    CatalogScenario::shopProduct($this->shop, ['name' => 'بطاطا']);

    expect(closeNamesOf($this->shop->id, 'Karote'))->toBe(['Carottes']);
    expect(closeNamesOf($this->shop->id, 'بطاطس'))->toBe(['بطاطا']);
});

it('ignores names that share only a short word or differ by 3 letters', function () {
    CatalogScenario::shopProduct($this->shop, ['name' => 'Huile de table']);
    CatalogScenario::shopProduct($this->shop, ['name' => 'Fromage']);

    expect(closeNamesOf($this->shop->id, 'Pâte de dattes'))->toBe([]);
    expect(closeNamesOf($this->shop->id, 'Frites'))->toBe([]);
});

it('finds checked catalog products by any of their names and spellings', function () {
    CatalogScenario::tomatoes();

    expect(closeNamesOf($this->shop->id, 'tmatim bio'))->toBe(['Tomate']);
    expect(closeNamesOf($this->shop->id, 'طماطم حارة'))->toBe(['Tomate']);
});

it('never returns a product waiting to be checked', function () {
    GlobalProduct::factory()->pending('Tomates')->create();

    expect(closeNamesOf($this->shop->id, 'Tomates'))->toBe([]);
});

it('never returns another shop product', function () {
    ['shop' => $otherShop] = CatalogScenario::merchantWithShop();
    CatalogScenario::shopProduct($otherShop, ['name' => 'Tomates cerises']);

    expect(closeNamesOf($this->shop->id, 'Tomates cerises'))->toBe([]);
});

it('leaves out the renamed product and its own catalog product', function () {
    $tomatoes = CatalogScenario::tomatoes();
    $renamed = CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates', 'global_product_id' => $tomatoes->id]);

    expect(closeNamesOf($this->shop->id, 'Tomates rondes', $renamed))->toBe([]);
});

it('returns at most 5 matches, closest first', function () {
    foreach (['Thé vert menthe', 'Thé vert', 'Thé noir', 'Thé vert de Chine', 'Thé', 'Thé rouge', 'Thé vert bio'] as $name) {
        CatalogScenario::shopProduct($this->shop, ['name' => $name]);
    }

    $matches = closeNamesOf($this->shop->id, 'Thé verts');

    expect($matches)->toHaveCount(5);
    expect($matches[0])->toBe('Thé vert');
});

it('merges a catalog product with the shop product linked to it', function () {
    $tomatoes = CatalogScenario::tomatoes();
    CatalogScenario::shopProduct($this->shop, ['name' => 'Tomates rondes', 'global_product_id' => $tomatoes->id]);

    $matches = app(FindCloseProducts::class)->handle($this->shop->id, 'Tomates allongées');

    expect($matches)->toHaveCount(1);
    expect($matches[0]->catalogProduct->id)->toBe($tomatoes->id);
    expect($matches[0]->shopProduct->name)->toBe('Tomates rondes');
});
