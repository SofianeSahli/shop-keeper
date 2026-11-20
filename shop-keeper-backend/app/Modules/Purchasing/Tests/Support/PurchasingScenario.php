<?php

namespace App\Modules\Purchasing\Tests\Support;

use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Shops\Models\Shop;
use App\Shared\Database\BinaryUuid;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

final class PurchasingScenario
{
    /**
     * @return array{shop: Shop, token: string}
     */
    public static function merchantWithShop(): array
    {
        return CatalogScenario::merchantWithShop();
    }

    public static function tokenOfMerchantWithoutShop(): string
    {
        return CatalogScenario::tokenOfMerchantWithoutShop();
    }

    public static function supplier(Shop $shop, array $attributes = []): Supplier
    {
        return Supplier::factory()->create(['shop_id' => $shop->id, ...$attributes]);
    }

    public static function product(Shop $shop, array $attributes = []): ShopProduct
    {
        return CatalogScenario::shopProduct($shop, $attributes);
    }

    public static function newId(): string
    {
        return (string) Str::uuid7();
    }

    public static function line(ShopProduct $product, int $quantity = 10, int $unitPrice = 1000): array
    {
        return ['id' => self::newId(), 'product_id' => $product->uuid(), 'quantity' => $quantity, 'unit_price' => $unitPrice];
    }

    public static function delivery(array $lines, array $fields = []): array
    {
        return ['id' => self::newId(), 'lines' => $lines, ...$fields];
    }

    public static function payment(Supplier $supplier, array $fields = []): array
    {
        return ['id' => self::newId(), 'supplier_id' => $supplier->uuid(), 'amount' => 10000, 'method' => 'cash', ...$fields];
    }

    public static function send(object $test, string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        app('auth')->forgetGuards();

        return $test->withToken($token ?? $test->token)->json($method, '/api/v1/'.$uri, $body);
    }

    public static function sendWithoutSession(object $test, string $method, string $uri, array $body = []): TestResponse
    {
        app('auth')->forgetGuards();

        return $test->withoutToken()->json($method, '/api/v1/'.$uri, $body);
    }

    public static function assertFieldError(TestResponse $response, string $field, string $code): void
    {
        $response->assertStatus(422)->assertJsonPath('error.code', 'validation.failed');
        $fields = $response->json('error.fields');
        $fieldCodes = $fields[$field] ?? null;

        expect($fieldCodes)->toBe([$code]);
    }

    public static function stockOf(ShopProduct $product): int
    {
        return $product->fresh()->stock_quantity;
    }

    public static function movementSumOf(ShopProduct $product): int
    {
        $productId = $product->uuid();
        $productBytes = BinaryUuid::toBytes($productId);

        return (int) StockMovement::query()->where('shop_product_id', $productBytes)->sum('quantity');
    }
}
