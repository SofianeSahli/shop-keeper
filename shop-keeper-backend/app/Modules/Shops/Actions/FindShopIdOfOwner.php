<?php

namespace App\Modules\Shops\Actions;

use App\Modules\Shops\Models\Shop;

final class FindShopIdOfOwner
{
    public function handle(int $ownerId): ?int
    {
        return Shop::query()->where('owner_id', $ownerId)->value('id');
    }
}
