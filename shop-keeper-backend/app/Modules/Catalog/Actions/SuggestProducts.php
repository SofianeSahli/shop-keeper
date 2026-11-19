<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\GlobalProductTerm;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Catalog\Support\ProductMatch;
use Illuminate\Support\Collection;

final class SuggestProducts
{
    public const MAX_SUGGESTIONS = 10;

    private const RANK_SQL = 'CASE WHEN %1$s = ? THEN 0 WHEN %1$s LIKE ? THEN 1 WHEN %1$s LIKE ? THEN 2 ELSE 3 END';

    /**
     * @return list<ProductMatch>
     */
    public function handle(int $shopId, string $search): array
    {
        $compared = NameComparison::compared($search);

        if ($compared === '') {
            return [];
        }

        $ranked = $this->rankedCatalogProducts($shopId, $compared);

        foreach ($this->rankedShopProducts($shopId, $compared) as $groupKey => $shopEntry) {
            $catalogEntry = $ranked->get($groupKey);
            $mergedEntry = $catalogEntry === null ? $shopEntry : $this->merged($catalogEntry, $shopEntry);
            $ranked->put($groupKey, $mergedEntry);
        }

        return $ranked
            ->sortBy([
                fn (array $first, array $second): int => $first['rank'] <=> $second['rank'],
                fn (array $first, array $second): int => ($first['match']->shopProduct === null) <=> ($second['match']->shopProduct === null),
                fn (array $first, array $second): int => $first['length'] <=> $second['length'],
                function (array $first, array $second): int {
                    $firstName = $first['match']->displayName();
                    $secondName = $second['match']->displayName();

                    return strcmp($firstName, $secondName);
                },
            ])
            ->take(self::MAX_SUGGESTIONS)
            ->map(fn (array $entry): ProductMatch => $entry['match'])
            ->values()
            ->all();
    }

    private function merged(array $catalogEntry, array $shopEntry): array
    {
        $bestRank = min($catalogEntry['rank'], $shopEntry['rank']);
        $shortestLength = min($catalogEntry['length'], $shopEntry['length']);

        return ['match' => $shopEntry['match'], 'rank' => $bestRank, 'length' => $shortestLength];
    }

    private function rankedCatalogProducts(int $shopId, string $compared): Collection
    {
        $rankSql = sprintf(self::RANK_SQL, 'compared');
        $rankBindings = $this->rankBindings($compared);
        $verifiedCatalogIds = GlobalProduct::query()->select('id')->where('status', CatalogStatus::Verified);
        $rows = GlobalProductTerm::query()
            ->selectRaw('global_product_id, MIN('.$rankSql.') AS match_rank, MIN(CHAR_LENGTH(compared)) AS match_length', $rankBindings)
            ->whereIn('global_product_id', $verifiedCatalogIds)
            ->where('compared', 'like', '%'.$compared.'%')
            ->groupBy('global_product_id')
            ->orderBy('match_rank')
            ->orderBy('match_length')
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        $catalogIds = $rows->pluck('global_product_id');
        $catalogProducts = GlobalProduct::query()->with('names')->findMany($catalogIds)->keyBy('id');
        $shopProductsByCatalogId = ShopProduct::query()
            ->ofShop($shopId)
            ->whereIn('global_product_id', $catalogIds)
            ->get()
            ->keyBy('global_product_id');

        return $rows->mapWithKeys(function (GlobalProductTerm $row) use ($catalogProducts, $shopProductsByCatalogId): array {
            $catalogProduct = $catalogProducts->get($row->global_product_id);
            $shopProduct = $shopProductsByCatalogId->get($row->global_product_id);
            $match = new ProductMatch($catalogProduct, $shopProduct);

            return [$match->groupKey() => ['match' => $match, 'rank' => (int) $row->match_rank, 'length' => (int) $row->match_length]];
        });
    }

    private function rankedShopProducts(int $shopId, string $compared): Collection
    {
        $rankSql = sprintf(self::RANK_SQL, 'compared_name');
        $rankBindings = $this->rankBindings($compared);

        return ShopProduct::query()
            ->ofShop($shopId)
            ->select('*')
            ->selectRaw($rankSql.' AS match_rank', $rankBindings)
            ->where('compared_name', 'like', '%'.$compared.'%')
            ->orderBy('match_rank')
            ->orderByRaw('CHAR_LENGTH(compared_name)')
            ->limit(self::MAX_SUGGESTIONS)
            ->with('globalProduct.names')
            ->get()
            ->mapWithKeys(function (ShopProduct $product): array {
                $match = ProductMatch::ofShopProduct($product);

                return [$match->groupKey() => ['match' => $match, 'rank' => (int) $product->match_rank, 'length' => mb_strlen($product->compared_name)]];
            });
    }

    private function rankBindings(string $compared): array
    {
        return [$compared, $compared.'%', '% '.$compared.'%'];
    }
}
