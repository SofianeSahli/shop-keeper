<?php

namespace App\Modules\Inventory\Tests\Support;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Shops\Models\Shop;
use App\Shared\Database\BinaryUuid;
use Illuminate\Testing\TestResponse;

final class InventoryScenario
{
    /**
     * @return array{shop: Shop, token: string}
     */
    public static function merchantWithShop(): array
    {
        return CatalogScenario::merchantWithShop();
    }

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

    public static function newId(): string
    {
        return ContactsScenario::newId();
    }

    public static function openingLine(ShopProduct $product, int $quantity = 10): array
    {
        $lineId = self::newId();
        $productId = $product->uuid();

        return ['id' => $lineId, 'product_id' => $productId, 'quantity' => $quantity];
    }

    public static function countLine(ShopProduct $product, int $countedQuantity, int $shownQuantity): array
    {
        $lineId = self::newId();
        $productId = $product->uuid();

        return ['id' => $lineId, 'product_id' => $productId, 'counted_quantity' => $countedQuantity, 'shown_quantity' => $shownQuantity];
    }

    public static function lossLine(ShopProduct $product, int $quantity = 1, string $reason = 'broken', ?string $note = null): array
    {
        $lineId = self::newId();
        $productId = $product->uuid();

        return ['id' => $lineId, 'product_id' => $productId, 'quantity' => $quantity, 'reason' => $reason, 'note' => $note];
    }

    public static function adjustment(array $lines, array $fields = []): array
    {
        $id = self::newId();

        return ['id' => $id, 'lines' => $lines, ...$fields];
    }

    public static function lineChanged(array $body, int $index, array $fields): array
    {
        $body['lines'][$index] = [...$body['lines'][$index], ...$fields];

        return $body;
    }

    public static function openStock(object $test, array $body, ?string $token = null): TestResponse
    {
        return ContactsScenario::send($test, 'POST', 'stock/openings', $body, $token);
    }

    public static function count(object $test, array $body, ?string $token = null): TestResponse
    {
        return ContactsScenario::send($test, 'POST', 'stock/counts', $body, $token);
    }

    public static function lose(object $test, array $body, ?string $token = null): TestResponse
    {
        return ContactsScenario::send($test, 'POST', 'stock/losses', $body, $token);
    }

    public static function history(object $test, ShopProduct $product, array $query = [], ?string $token = null): TestResponse
    {
        $productId = $product->uuid();
        $queryString = http_build_query($query);
        $uri = 'products/'.$productId.'/stock-movements?'.$queryString;

        return ContactsScenario::send($test, 'GET', $uri, [], $token);
    }

    public static function stockOf(ShopProduct $product): int
    {
        $freshProduct = $product->fresh();

        return $freshProduct->stock_quantity;
    }

    public static function sumOfMovements(ShopProduct $product): int
    {
        $productId = $product->uuid();
        $productBytes = BinaryUuid::toBytes($productId);
        $sum = StockMovement::query()->where('shop_product_id', $productBytes)->sum('quantity');

        return (int) $sum;
    }
}
