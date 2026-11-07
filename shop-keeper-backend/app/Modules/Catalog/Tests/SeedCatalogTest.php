<?php

use App\Modules\Catalog\Actions\SuggestProducts;
use App\Modules\Catalog\Console\LoadCatalogCommand;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Support\ProductMatch;
use App\Modules\Catalog\Tests\Support\CatalogScenario;

const MIN_SEEDED_PRODUCTS = 150;
const MIN_FOUND_SHARE = 0.95;
const SEARCH_PREFIX_LENGTH = 3;

function seededEntries(): array
{
    return json_decode(file_get_contents(base_path(LoadCatalogCommand::DEFAULT_FILE)), true, flags: JSON_THROW_ON_ERROR);
}

function searchesOf(array $entry): array
{
    return [
        mb_substr($entry['names']['fr'], 0, SEARCH_PREFIX_LENGTH),
        mb_substr($entry['names']['ar'], 0, SEARCH_PREFIX_LENGTH),
        ...array_merge(...array_values($entry['spellings'] ?? [])),
    ];
}

it('loads at least 150 checked products without a barcode', function () {
    $this->artisan('catalog:load')->assertSuccessful();

    expect(GlobalProduct::query()->whereNull('barcode')->count())->toBeGreaterThanOrEqual(MIN_SEEDED_PRODUCTS);
    expect(GlobalProduct::query()->where('status', '!=', 'verified')->count())->toBe(0);
});

it('puts each seeded product in the first 10 suggestions for 95% of searches', function () {
    $this->artisan('catalog:load')->assertSuccessful();
    ['shop' => $shop] = CatalogScenario::merchantWithShop();
    $idsByKey = GlobalProduct::query()->pluck('id', 'translation_key');
    $suggestProducts = app(SuggestProducts::class);
    $searches = 0;
    $missed = [];

    foreach (seededEntries() as $entry) {
        foreach (searchesOf($entry) as $search) {
            $searches++;
            $foundIds = array_map(fn (ProductMatch $match): ?int => $match->catalogProduct?->id, $suggestProducts->handle($shop->id, $search));

            if (! in_array($idsByKey[$entry['key']], $foundIds, true)) {
                $missed[] = "{$entry['key']} <- {$search}";
            }
        }
    }

    expect(1 - count($missed) / $searches)->toBeGreaterThanOrEqual(MIN_FOUND_SHARE, implode(PHP_EOL, $missed));
});
