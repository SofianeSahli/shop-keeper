<?php

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\SupplierPayment;
use App\Shared\CurrentShop;

final class SupplierPaymentPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, SupplierPayment $payment): bool
    {
        return $payment->shop_id === $this->currentShop->shopId;
    }

    public function cancel(User $user, SupplierPayment $payment): bool
    {
        return $payment->shop_id === $this->currentShop->shopId;
    }
}
