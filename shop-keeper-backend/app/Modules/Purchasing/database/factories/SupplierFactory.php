<?php

namespace App\Modules\Purchasing\database\factories;

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Shops\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->regexify('[A-Z][a-z]{9} [a-z]{8}'),
            'phone' => null,
        ];
    }
}
