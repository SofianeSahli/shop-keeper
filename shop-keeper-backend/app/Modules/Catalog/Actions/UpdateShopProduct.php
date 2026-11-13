<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Catalog\Support\ShopProductChanges;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateShopProduct
{
    public function __construct(private ShopProductNameRules $nameRules) {}

    public function handle(ShopProduct $product, ShopProductChanges $changes): ShopProduct
    {
        return DB::transaction(function () use ($product, $changes): ShopProduct {
            $locked = ShopProduct::query()->whereUuid($product->uuid())->lockForUpdate()->with('globalProduct.names')->firstOrFail();

            if ($this->isRenamed($locked, $changes)) {
                $this->checkNewName($locked, $changes);
                $locked->name = $changes->name;
            }

            if ($changes->salePrice !== null) {
                $locked->sale_price = $changes->salePrice;
            }

            if ($changes->changesPurchasePrice) {
                $locked->purchase_price = $changes->purchasePrice;
            }

            $this->save($locked);

            return $locked;
        });
    }

    private function isRenamed(ShopProduct $product, ShopProductChanges $changes): bool
    {
        return $changes->name !== null && $changes->name !== $product->name;
    }

    private function checkNewName(ShopProduct $product, ShopProductChanges $changes): void
    {
        if (NameComparison::compared($changes->name) === $product->compared_name) {
            return;
        }

        $this->nameRules->refuseIfNameTaken($product->shop_id, $changes->name, exceptProductId: $product->uuid());

        if ($product->globalProduct?->barcode === null) {
            $this->nameRules->holdIfClose($product->shop_id, $changes->name, $changes->confirmedDifferent, $product);
        }
    }

    private function save(ShopProduct $product): void
    {
        try {
            $product->save();
        } catch (UniqueConstraintViolationException $lostRace) {
            $this->nameRules->refuseIfNameTaken($product->shop_id, $product->name, exceptProductId: $product->uuid(), readLatest: true);

            throw $lostRace;
        }
    }
}
