<?php

namespace App\Modules\Shops\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;

final class ShopPolicy
{
    public function view(User $user, Shop $shop): bool
    {
        return $shop->owner_id === $user->id;
    }
}
