<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\CreatedShopProduct;
use App\Modules\Catalog\Support\NewShopProduct;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateShopProduct
{
    public function __construct(
        private FindOrCreatePendingProduct $findOrCreatePendingProduct,
        private ShopProductNameRules $nameRules,
    ) {}

    public function handle(NewShopProduct $new): CreatedShopProduct
    {
        return DB::transaction(function () use ($new): CreatedShopProduct {
            $productWithSameId = $this->productWithSameId($new);

            if ($productWithSameId !== null) {
                return new CreatedShopProduct($productWithSameId, wasCreated: false);
            }

            $catalogProduct = $this->linkedCatalogProduct($new);
            $name = $new->name ?? $catalogProduct->nameIn($new->language);

            if ($catalogProduct !== null) {
                $this->nameRules->refuseIfAlreadyInShop($new->shopId, $catalogProduct);
            }

            $this->nameRules->refuseIfNameTaken($new->shopId, $name);

            if ($catalogProduct === null) {
                $this->nameRules->holdIfClose($new->shopId, $name, $new->confirmedDifferent);
            }

            return $this->insert($new, $catalogProduct, $name);
        });
    }

    private function productWithSameId(NewShopProduct $new, bool $readLatest = false): ?ShopProduct
    {
        $product = ShopProduct::query()
            ->whereUuid($new->id)
            ->when($readLatest, fn ($query) => $query->lockForUpdate())
            ->with('globalProduct.names')
            ->first();

        if ($product !== null && $product->shop_id !== $new->shopId) {
            throw ValidationException::withMessages(['id' => ['id.taken']]);
        }

        return $product;
    }

    private function linkedCatalogProduct(NewShopProduct $new): ?GlobalProduct
    {
        if ($new->barcode !== null) {
            return $this->catalogProductOfBarcode($new);
        }

        if ($new->catalogProductId !== null) {
            return $this->verifiedCatalogProduct($new);
        }

        $this->requireFields($new, nameRequired: true, languageRequired: false);

        return null;
    }

    private function catalogProductOfBarcode(NewShopProduct $new): GlobalProduct
    {
        $catalogProduct = GlobalProduct::query()->with('names')->where('barcode', $new->barcode->value)->first();

        if ($catalogProduct === null) {
            $this->requireFields($new, nameRequired: true, languageRequired: true);

            return $this->findOrCreatePendingProduct->handle($new->barcode, $new->name, $new->language);
        }

        $this->requireFields($new, nameRequired: ! $catalogProduct->isVerified(), languageRequired: $new->name === null);

        return $catalogProduct;
    }

    private function verifiedCatalogProduct(NewShopProduct $new): GlobalProduct
    {
        $catalogProduct = GlobalProduct::query()
            ->with('names')
            ->where('status', CatalogStatus::Verified)
            ->find($new->catalogProductId);

        if ($catalogProduct === null) {
            throw ValidationException::withMessages(['catalog_product_id' => ['catalog_product_id.unknown']]);
        }

        $this->requireFields($new, nameRequired: false, languageRequired: $new->name === null);

        return $catalogProduct;
    }

    private function requireFields(NewShopProduct $new, bool $nameRequired, bool $languageRequired): void
    {
        $missing = array_filter([
            'name' => $nameRequired && $new->name === null ? ['name.required'] : null,
            'language' => $languageRequired && $new->language === null ? ['language.required'] : null,
        ]);

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }
    }

    private function insert(NewShopProduct $new, ?GlobalProduct $catalogProduct, string $name): CreatedShopProduct
    {
        try {
            $product = ShopProduct::query()->create([
                'id' => $new->id,
                'shop_id' => $new->shopId,
                'global_product_id' => $catalogProduct?->id,
                'name' => $name,
                'sale_price' => $new->salePrice,
                'purchase_price' => $new->purchasePrice,
            ]);
        } catch (UniqueConstraintViolationException $lostRace) {
            return $this->answerAfterLostRace($new, $catalogProduct, $name, $lostRace);
        }

        return new CreatedShopProduct($product->setRelation('globalProduct', $catalogProduct), wasCreated: true);
    }

    private function answerAfterLostRace(
        NewShopProduct $new,
        ?GlobalProduct $catalogProduct,
        string $name,
        UniqueConstraintViolationException $lostRace,
    ): CreatedShopProduct {
        $productWithSameId = $this->productWithSameId($new, readLatest: true);

        if ($productWithSameId !== null) {
            return new CreatedShopProduct($productWithSameId, wasCreated: false);
        }

        if ($catalogProduct !== null) {
            $this->nameRules->refuseIfAlreadyInShop($new->shopId, $catalogProduct, readLatest: true);
        }

        $this->nameRules->refuseIfNameTaken($new->shopId, $name, readLatest: true);

        throw $lostRace;
    }
}
