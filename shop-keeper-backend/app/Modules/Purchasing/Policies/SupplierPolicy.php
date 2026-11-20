<?php

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\Supplier;
use App\Shared\CurrentShop;

final class SupplierPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, Supplier $supplier): bool
    {
        return $supplier->shop_id === $this->currentShop->shopId;
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $supplier->shop_id === $this->currentShop->shopId;
    }
}
