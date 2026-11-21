<?php

namespace App\Modules\Payments\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Shared\CurrentShop;

final class PaymentPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, Payment $payment): bool
    {
        return $payment->shop_id === $this->currentShop->shopId;
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $payment->shop_id === $this->currentShop->shopId;
    }
}
