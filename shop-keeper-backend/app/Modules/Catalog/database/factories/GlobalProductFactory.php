<?php

namespace App\Modules\Catalog\database\factories;

use App\Modules\Catalog\Actions\ReplaceCatalogNames;
use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Support\Barcode;
use Illuminate\Database\Eloquent\Factories\Factory;

class GlobalProductFactory extends Factory
{
    protected $model = GlobalProduct::class;

    public function definition(): array
    {
        return [
            'translation_key' => 'products.'.fake()->unique()->regexify('[a-z]{12}'),
            'status' => CatalogStatus::Verified,
            'barcode' => null,
        ];
    }

    public function named(string $french, string $arabic, string $english, array $spellings = []): self
    {
        return $this->afterCreating(function (GlobalProduct $product) use ($french, $arabic, $english, $spellings): void {
            app(ReplaceCatalogNames::class)->handle($product, [
                CatalogLanguage::French->value => $french,
                CatalogLanguage::Arabic->value => $arabic,
                CatalogLanguage::English->value => $english,
            ], $spellings);
        });
    }

    public function withBarcode(?string $barcode = null): self
    {
        return $this->state(fn (): array => ['barcode' => $barcode ?? self::randomBarcode()]);
    }

    public function pending(string $typedName = 'Typed by a shop', CatalogLanguage $language = CatalogLanguage::French): self
    {
        $barcode = self::randomBarcode();

        return $this->state(fn (): array => [
            'translation_key' => GlobalProduct::pendingKeyFor($barcode),
            'status' => CatalogStatus::Pending,
            'barcode' => $barcode,
            'pending_name' => $typedName,
            'pending_language' => $language->value,
        ]);
    }

    public static function randomBarcode(string $prefix = '619'): string
    {
        $digits = $prefix.fake()->numerify(str_repeat('#', 12 - strlen($prefix)));

        return Barcode::parse($digits.Barcode::checkDigit($digits))->value;
    }
}
