<?php

namespace App\Modules\Contacts\database\factories;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Shops\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->regexify('[A-Z][a-z]{9} [a-z]{8}'),
            'phone' => null,
            'address' => null,
            'note' => null,
            'is_supplier' => false,
            'is_customer' => false,
        ];
    }

    public function supplier(): self
    {
        return $this->state(['is_supplier' => true]);
    }

    public function customer(): self
    {
        return $this->state(['is_customer' => true]);
    }
}
