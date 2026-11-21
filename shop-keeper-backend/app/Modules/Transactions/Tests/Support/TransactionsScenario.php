<?php

namespace App\Modules\Transactions\Tests\Support;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Shops\Models\Shop;
use App\Shared\Database\BinaryUuid;
use Illuminate\Testing\TestResponse;

final class TransactionsScenario
{
    public static function product(Shop $shop, array $attributes = []): ShopProduct
    {
        return CatalogScenario::shopProduct($shop, $attributes);
    }

    /**
     * @return list<ShopProduct>
     */
    public static function products(Shop $shop, int $count): array
    {
        $products = ShopProduct::factory()->count($count)->create(['shop_id' => $shop->id]);

        return $products->all();
    }

    public static function line(ShopProduct $product, int $quantity = 10, int $unitPrice = 1000): array
    {
        $lineId = ContactsScenario::newId();
        $productId = $product->uuid();

        return ['id' => $lineId, 'product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice];
    }

    public static function purchase(array $lines, array $fields = []): array
    {
        $id = ContactsScenario::newId();

        return ['id' => $id, 'kind' => 'purchase', 'lines' => $lines, ...$fields];
    }

    public static function record(object $test, array $body, ?string $token = null): TestResponse
    {
        return ContactsScenario::send($test, 'POST', 'transactions', $body, $token);
    }

    public static function stockOf(ShopProduct $product): int
    {
        $freshProduct = $product->fresh();

        return $freshProduct->stock_quantity;
    }

    public static function movementSumOf(ShopProduct $product): int
    {
        $productId = $product->uuid();
        $productBytes = BinaryUuid::toBytes($productId);
        $sum = StockMovement::query()->where('shop_product_id', $productBytes)->sum('quantity');

        return (int) $sum;
    }
}
