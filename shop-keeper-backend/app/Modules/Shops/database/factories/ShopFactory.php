<?php

namespace App\Modules\Shops\database\factories;

use App\Modules\Shops\Models\BusinessType;
use App\Modules\Shops\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'business_type' => fake()->randomElement(BusinessType::cases())->value,
            'currency' => Shop::DEFAULT_CURRENCY,
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
        ];
    }
}
