<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Controllers\Resources\SuggestionResource;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Catalog\Support\ProductMatch;
use App\Shared\Errors\ApiException;
use Illuminate\Database\Eloquent\Builder;

final class ShopProductNameRules
{
    private const CONFLICT_STATUS = 409;

    public function __construct(private FindCloseProducts $findCloseProducts) {}

    public function refuseIfAlreadyInShop(int $shopId, GlobalProduct $catalogProduct, bool $readLatest = false): void
    {
        $existing = $this->latest(
            ShopProduct::query()->ofShop($shopId)->where('global_product_id', $catalogProduct->id),
            $readLatest,
        )->first();

        if ($existing !== null) {
            throw $this->duplicate('product.already_in_shop', $existing);
        }
    }

    public function refuseIfNameTaken(int $shopId, string $name, ?string $exceptProductId = null, bool $readLatest = false): void
    {
        $existing = $this->latest(
            ShopProduct::query()
                ->ofShop($shopId)
                ->where('identity_key', NameComparison::identityKey($name))
                ->when($exceptProductId !== null, fn (Builder $query) => $query->whereNot(fn (Builder $excluded) => $excluded->whereUuid($exceptProductId))),
            $readLatest,
        )->first();

        if ($existing !== null) {
            throw $this->duplicate('product.name_taken', $existing);
        }
    }

    public function holdIfClose(int $shopId, string $name, bool $confirmedDifferent, ?ShopProduct $renamedProduct = null): void
    {
        if ($confirmedDifferent) {
            return;
        }

        $matches = $this->findCloseProducts->handle($shopId, $name, $renamedProduct);

        if ($matches !== []) {
            throw new ApiException('product.name_close', self::CONFLICT_STATUS, details: [
                'matches' => array_map(
                    fn (ProductMatch $match): array => (new SuggestionResource($match))->resolve(),
                    $matches,
                ),
            ]);
        }
    }

    private function latest(Builder $query, bool $readLatest): Builder
    {
        return $readLatest ? $query->lockForUpdate() : $query;
    }

    private function duplicate(string $code, ShopProduct $existing): ApiException
    {
        return new ApiException($code, self::CONFLICT_STATUS, details: ['existing_product_id' => $existing->uuid()]);
    }
}
