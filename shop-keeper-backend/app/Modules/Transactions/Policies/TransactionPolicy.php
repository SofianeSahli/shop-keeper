<?php

namespace App\Modules\Transactions\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Transactions\Models\Transaction;
use App\Shared\CurrentShop;

final class TransactionPolicy
{
    public function __construct(private CurrentShop $currentShop) {}

    public function view(User $user, Transaction $transaction): bool
    {
        return $transaction->shop_id === $this->currentShop->shopId;
    }

    public function cancel(User $user, Transaction $transaction): bool
    {
        return $transaction->shop_id === $this->currentShop->shopId;
    }
}
