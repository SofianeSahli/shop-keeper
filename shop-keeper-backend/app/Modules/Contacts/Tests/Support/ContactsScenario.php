<?php

namespace App\Modules\Contacts\Tests\Support;

use App\Modules\Catalog\Tests\Support\CatalogScenario;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Shops\Models\Shop;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

final class ContactsScenario
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

    public static function newId(): string
    {
        return (string) Str::uuid7();
    }

    public static function contact(Shop $shop, array $attributes = []): Contact
    {
        return Contact::factory()->create(['shop_id' => $shop->id, ...$attributes]);
    }

    public static function creation(array $fields = []): array
    {
        $id = self::newId();

        return ['id' => $id, 'name' => 'Grossiste Ben Ali', ...$fields];
    }

    public static function send(object $test, string $method, string $uri, array $body = [], ?string $token = null): TestResponse
    {
        app('auth')->forgetGuards();
        $bearer = $token ?? $test->token;
        $url = '/api/v1/'.$uri;

        return $test->withToken($bearer)->json($method, $url, $body);
    }

    public static function sendWithoutSession(object $test, string $method, string $uri, array $body = []): TestResponse
    {
        app('auth')->forgetGuards();
        $url = '/api/v1/'.$uri;

        return $test->withoutToken()->json($method, $url, $body);
    }

    public static function assertFieldError(TestResponse $response, string $field, string $code): void
    {
        $response->assertStatus(422)->assertJsonPath('error.code', 'validation.failed');
        $fields = $response->json('error.fields');
        $fieldCodes = $fields[$field] ?? null;

        expect($fieldCodes)->toBe([$code]);
    }
}
