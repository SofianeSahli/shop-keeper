<?php

use App\Modules\Catalog\database\factories\GlobalProductFactory;
use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\GlobalProductName;
use App\Modules\Catalog\Models\GlobalProductTerm;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;

const CATALOG_FIXTURES = 'app/Modules/Catalog/Tests/fixtures';
const GENERATED_CATALOGS = 'storage/framework/testing/catalogs';

function loadCatalog(object $test, string $file): PendingCommand
{
    $path = str_contains($file, '/') ? $file : CATALOG_FIXTURES.'/'.$file;

    return $test->artisan('catalog:load', ['file' => $path]);
}

function catalogFixture(array|string $entries): string
{
    $contents = is_string($entries) ? $entries : json_encode($entries, JSON_UNESCAPED_UNICODE);
    $path = GENERATED_CATALOGS.'/'.md5($contents).'.json';
    file_put_contents(base_path($path), $contents);

    return $path;
}

function catalogSnapshot(): array
{
    return [
        'products' => DB::table('global_products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'names' => DB::table('global_product_names')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'terms' => DB::table('global_product_terms')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
    ];
}

function entry(string $key, array $overrides = []): array
{
    return ['key' => $key, 'names' => ['fr' => 'Nom', 'ar' => 'اسم', 'en' => 'Name'], ...$overrides];
}

beforeEach(function () {
    if (! is_dir(base_path(GENERATED_CATALOGS))) {
        mkdir(base_path(GENERATED_CATALOGS), recursive: true);
    }
});

afterEach(function () {
    array_map(unlink(...), glob(base_path(GENERATED_CATALOGS).'/*.json'));
});

it('loads checked products with their three names and their search terms', function () {
    loadCatalog($this, 'catalog-valid.json')
        ->expectsOutputToContain('4 added, 0 updated, 0 unchanged')
        ->assertSuccessful();

    $tomatoes = GlobalProduct::query()->where('translation_key', 'products.tomato')->sole();
    expect($tomatoes->status)->toBe(CatalogStatus::Verified);
    expect($tomatoes->barcode)->toBeNull();
    expect($tomatoes->namesByLanguage())->toBe(['fr' => 'Tomate', 'ar' => 'طماطم', 'en' => 'Tomato']);
    expect($tomatoes->terms()->pluck('compared')->sort()->values()->all())->toBe(['tmatem', 'tomate', 'tomate', 'tomato', 'طماطم', 'طماطه']);
    expect(GlobalProduct::query()->where('barcode', '6191234567897')->value('translation_key'))->toBe('products.example_biscuit');
});

it('changes nothing when the same file is loaded again', function () {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    $before = catalogSnapshot();
    Carbon::setTestNow(now()->addHour());

    loadCatalog($this, 'catalog-valid.json')
        ->expectsOutputToContain('0 added, 0 updated, 4 unchanged')
        ->assertSuccessful();

    expect(catalogSnapshot())->toBe($before);
});

it('adds new keys and updates changed ones in place', function () {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    $tomatoId = GlobalProduct::query()->where('translation_key', 'products.tomato')->value('id');

    loadCatalog($this, 'catalog-updated.json')
        ->expectsOutputToContain('1 added, 2 updated, 2 unchanged')
        ->assertSuccessful();

    $tomatoes = GlobalProduct::query()->where('translation_key', 'products.tomato')->sole();
    expect($tomatoes->id)->toBe($tomatoId);
    expect($tomatoes->nameIn(CatalogLanguage::French))->toBe('Tomate ronde');
    expect(GlobalProductTerm::query()->where('compared', 'bagat')->exists())->toBeTrue();
    expect(GlobalProduct::query()->where('translation_key', 'products.onion')->exists())->toBeTrue();
    expect(GlobalProduct::query()->count())->toBe(5);
});

it('stops on a wrong entry, says which one, and changes nothing', function (array $entries, string $expectedError) {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    $before = catalogSnapshot();

    loadCatalog($this, catalogFixture($entries))
        ->expectsOutputToContain($expectedError)
        ->assertFailed();

    expect(catalogSnapshot())->toBe($before);
})->with([
    'bad key' => [[entry('products.fine'), entry('Products.Bad-Key')], 'entry 2 (Products.Bad-Key): key.invalid'],
    'missing Arabic name' => [[entry('products.no_arabic', ['names' => ['fr' => 'Nom', 'en' => 'Name']])], 'entry 1 (products.no_arabic): name.missing'],
    'name too long' => [[entry('products.long', ['names' => ['fr' => str_repeat('a', 121), 'ar' => 'اسم', 'en' => 'Name']])], 'entry 1 (products.long): name.too_long'],
    'duplicate key' => [[entry('products.twice'), entry('products.twice')], 'entry 2 (products.twice): key.duplicate'],
    'duplicate barcode' => [[entry('products.one', ['barcode' => '6191234567897']), entry('products.two', ['barcode' => '6191234567897'])], 'entry 2 (products.two): barcode.duplicate'],
    'invalid barcode' => [[entry('products.bad_code', ['barcode' => '6191234567890'])], 'entry 1 (products.bad_code): barcode.invalid'],
    'in-store barcode' => [[entry('products.in_store', ['barcode' => '2001234567893'])], 'entry 1 (products.in_store): barcode.in_store'],
    'barcode of another checked product' => [[entry('products.other_key', ['barcode' => '6191234567897'])], 'entry 1 (products.other_key): barcode.taken'],
    'changed barcode' => [[entry('products.example_biscuit', ['barcode' => '96385074'])], 'entry 1 (products.example_biscuit): barcode.changed'],
]);

it('stops on a file that is not a list of entries', function () {
    loadCatalog($this, catalogFixture('{ not json'))->expectsOutputToContain('file.invalid')->assertFailed();
    loadCatalog($this, 'missing.json')->expectsOutputToContain('file.invalid')->assertFailed();
});

it('keeps the shops own names when a catalog name changes', function () {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    ['shop' => $shop] = CatalogScenario::merchantWithShop();
    $tomatoes = GlobalProduct::query()->where('translation_key', 'products.tomato')->sole();
    $product = CatalogScenario::shopProduct($shop, ['global_product_id' => $tomatoes->id, 'name' => 'Tomate']);

    loadCatalog($this, 'catalog-updated.json')->assertSuccessful();

    expect($product->fresh()->name)->toBe('Tomate');
});

it('checks a pending barcode in place and keeps the shops linked to it', function () {
    $pending = GlobalProduct::factory()->pending('Biscuit typed')->create();
    ['shop' => $shop] = CatalogScenario::merchantWithShop();
    $product = CatalogScenario::shopProduct($shop, ['global_product_id' => $pending->id, 'name' => 'Biscuit du coin']);

    loadCatalog($this, catalogFixture([entry('products.choco_biscuit', ['barcode' => $pending->barcode])]))
        ->expectsOutputToContain('0 added, 1 updated, 0 unchanged')
        ->assertSuccessful();

    $checked = $pending->fresh();
    expect($checked->status)->toBe(CatalogStatus::Verified);
    expect($checked->translation_key)->toBe('products.choco_biscuit');
    expect($checked->pending_name)->toBeNull();
    expect($checked->pending_language)->toBeNull();
    expect(GlobalProductName::query()->where('global_product_id', $pending->id)->count())->toBe(3);
    expect($product->fresh()->global_product_id)->toBe($pending->id);
    expect($product->fresh()->name)->toBe('Biscuit du coin');
});

it('stops when the key of a pending barcode is already used', function () {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    $pending = GlobalProduct::factory()->pending()->create();
    $before = catalogSnapshot();

    loadCatalog($this, catalogFixture([entry('products.tomato', ['barcode' => $pending->barcode])]))
        ->expectsOutputToContain('entry 1 (products.tomato): key.taken')
        ->assertFailed();

    expect(catalogSnapshot())->toBe($before);
});

it('adds a barcode to a key that had none', function () {
    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();
    $barcode = GlobalProductFactory::randomBarcode();

    loadCatalog($this, catalogFixture([entry('products.egg', ['barcode' => $barcode, 'names' => ['fr' => 'Œuf', 'ar' => 'بيض', 'en' => 'Egg']])]))
        ->assertSuccessful();

    expect(GlobalProduct::query()->where('translation_key', 'products.egg')->value('barcode'))->toBe($barcode);
});

it('never deletes keys missing from the file', function () {
    loadCatalog($this, 'catalog-updated.json')->assertSuccessful();

    loadCatalog($this, 'catalog-valid.json')->assertSuccessful();

    expect(GlobalProduct::query()->where('translation_key', 'products.onion')->exists())->toBeTrue();
});
