<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\GlobalProductTerm;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\CloseScore;
use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Catalog\Support\ProductMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class FindCloseProducts
{
    public const MAX_CLOSE_MATCHES = 5;

    /**
     * @return list<ProductMatch>
     */
    public function handle(int $shopId, string $name, ?ShopProduct $renamedProduct = null): array
    {
        $compared = NameComparison::compared($name);

        if ($compared === '') {
            return [];
        }

        $catalogCandidates = $this->catalogCandidates($shopId, $compared, $renamedProduct?->global_product_id);
        $shopCandidates = $this->shopCandidates($shopId, $compared, $renamedProduct);
        $scoredMatches = collect([...$catalogCandidates, ...$shopCandidates]);

        return $this->bestPerProduct($scoredMatches)
            ->sortBy([
                fn (array $first, array $second): int => $first['score']->compare($second['score']),
                function (array $first, array $second): int {
                    $firstName = $first['match']->displayName();
                    $secondName = $second['match']->displayName();

                    return strcmp($firstName, $secondName);
                },
            ])
            ->take(self::MAX_CLOSE_MATCHES)
            ->map(fn (array $scored): ProductMatch => $scored['match'])
            ->values()
            ->all();
    }

    private function catalogCandidates(int $shopId, string $compared, ?int $excludedCatalogProductId): array
    {
        $verifiedCatalogIds = GlobalProduct::query()->select('id')->where('status', CatalogStatus::Verified);
        $terms = GlobalProductTerm::query()
            ->whereIn('global_product_id', $verifiedCatalogIds)
            ->when($excludedCatalogProductId !== null, fn (Builder $query) => $query->where('global_product_id', '!=', $excludedCatalogProductId))
            ->where(fn (Builder $query) => $this->narrowToPossiblyClose($query, 'compared', $compared))
            ->with('globalProduct.names')
            ->get();

        $catalogIds = $terms->pluck('global_product_id')->unique();
        $shopProductsByCatalogId = ShopProduct::query()
            ->ofShop($shopId)
            ->whereIn('global_product_id', $catalogIds)
            ->get()
            ->keyBy('global_product_id');

        return $terms
            ->map(function (GlobalProductTerm $term) use ($compared, $shopProductsByCatalogId): ?array {
                $shopProduct = $shopProductsByCatalogId->get($term->global_product_id);
                $match = new ProductMatch($term->globalProduct, $shopProduct);
                $score = CloseScore::between($compared, $term->compared);

                return $this->scored($match, $score);
            })
            ->filter()
            ->all();
    }

    private function shopCandidates(int $shopId, string $compared, ?ShopProduct $renamedProduct): array
    {
        $renamedProductId = $renamedProduct?->uuid();

        return ShopProduct::query()
            ->ofShop($shopId)
            ->when($renamedProductId !== null, fn (Builder $query) => $query->whereNot(fn (Builder $excluded) => $excluded->whereUuid($renamedProductId)))
            ->where(fn (Builder $query) => $this->narrowToPossiblyClose($query, 'compared_name', $compared))
            ->with('globalProduct.names')
            ->get()
            ->map(function (ShopProduct $product) use ($compared): ?array {
                $match = ProductMatch::ofShopProduct($product);
                $score = CloseScore::between($compared, $product->compared_name);

                return $this->scored($match, $score);
            })
            ->filter()
            ->all();
    }

    private function narrowToPossiblyClose(Builder $query, string $column, string $compared): void
    {
        $length = mb_strlen($compared);

        $query->whereRaw(
            "CHAR_LENGTH({$column}) BETWEEN ? AND ?",
            [$length - CloseScore::MAX_LETTER_DIFFERENCE, $length + CloseScore::MAX_LETTER_DIFFERENCE],
        );

        if ($length >= CloseScore::MIN_SHARED_LENGTH) {
            $query->orWhere($column, 'like', '%'.$compared.'%');
        }

        $query->orWhereRaw(
            "CHAR_LENGTH({$column}) >= ? AND ? LIKE CONCAT('%', {$column}, '%')",
            [CloseScore::MIN_SHARED_LENGTH, $compared],
        );

        foreach (CloseScore::significantWords($compared) as $word) {
            $query->orWhereRaw("CONCAT(' ', {$column}, ' ') LIKE ?", ['% '.$word.' %']);
        }
    }

    private function scored(ProductMatch $match, ?CloseScore $score): ?array
    {
        return $score === null ? null : ['match' => $match, 'score' => $score];
    }

    private function bestPerProduct(Collection $scoredMatches): Collection
    {
        return $scoredMatches
            ->groupBy(fn (array $scored): string => $scored['match']->groupKey())
            ->map(fn (Collection $group): array => [
                'match' => $group->first(fn (array $scored): bool => $scored['match']->shopProduct !== null)['match'] ?? $group->first()['match'],
                'score' => $group->map(fn (array $scored): CloseScore => $scored['score'])
                    ->sort(fn (CloseScore $first, CloseScore $second): int => $first->compare($second))
                    ->first(),
            ])
            ->values();
    }
}
