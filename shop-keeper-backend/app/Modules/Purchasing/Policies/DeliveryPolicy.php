<?php

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\Delivery;
use App\Shared\CurrentShop;

final class DeliveryPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, Delivery $delivery): bool
    {
        return $delivery->shop_id === $this->currentShop->shopId;
    }

    public function cancel(User $user, Delivery $delivery): bool
    {
        return $delivery->shop_id === $this->currentShop->shopId;
    }
}
