<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Identity\Models\User;
use App\Shared\CurrentShop;

final class ShopProductPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, ShopProduct $product): bool
    {
        return $product->shop_id === $this->currentShop->shopId;
    }

    public function update(User $user, ShopProduct $product): bool
    {
        return $product->shop_id === $this->currentShop->shopId;
    }
}
