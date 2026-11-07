<?php

namespace App\Modules\Catalog\Tests\Support;

use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;
use Illuminate\Support\Str;

final class CatalogScenario
{
    public const SALE_PRICE = 1800;

    /**
     * @return array{shop: Shop, token: string}
     */
    public static function merchantWithShop(): array
    {
        $merchant = User::factory()->create();

        return [
            'shop' => Shop::factory()->create(['owner_id' => $merchant->id]),
            'token' => $merchant->createToken((string) Str::uuid())->plainTextToken,
        ];
    }

    public static function tokenOfMerchantWithoutShop(): string
    {
        return User::factory()->create()->createToken((string) Str::uuid())->plainTextToken;
    }

    public static function tomatoes(?string $barcode = null): GlobalProduct
    {
        return GlobalProduct::factory()
            ->state(['translation_key' => 'products.tomato', 'barcode' => $barcode])
            ->named('Tomate', 'طماطم', 'Tomato', ['fr' => ['tomates'], 'ar' => ['طماطة', 'tmatem', 'tmatim']])
            ->create();
    }

    public static function shopProduct(Shop $shop, array $attributes = []): ShopProduct
    {
        return ShopProduct::factory()->create(['shop_id' => $shop->id, ...$attributes]);
    }

    public static function creation(array $fields = []): array
    {
        return ['id' => (string) Str::uuid7(), 'sale_price' => self::SALE_PRICE, ...$fields];
    }
}
