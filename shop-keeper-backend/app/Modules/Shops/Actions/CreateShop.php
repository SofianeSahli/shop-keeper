<?php

namespace App\Modules\Shops\Actions;

use App\Modules\Shops\Models\Shop;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateShop
{
    public function handle(int $ownerId, string $name, string $businessType, ?string $city, ?string $address): Shop
    {
        try {
            return Shop::query()->create([
                'owner_id' => $ownerId,
                'name' => $name,
                'business_type' => $businessType,
                'currency' => Shop::DEFAULT_CURRENCY,
                'city' => $city,
                'address' => $address,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ApiException('shop.already_exists', 409);
        }
    }
}
