<?php

namespace App\Modules\Catalog\database\factories;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Shops\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShopProductFactory extends Factory
{
    protected $model = ShopProduct::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'shop_id' => Shop::factory(),
            'global_product_id' => null,
            'name' => fake()->unique()->regexify('[A-Z][a-z]{9} [a-z]{8}'),
            'sale_price' => fake()->numberBetween(100, 50000),
            'purchase_price' => null,
        ];
    }
}
