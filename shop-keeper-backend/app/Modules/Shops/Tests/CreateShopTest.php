<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;

beforeEach(function () {
    $this->merchant = User::factory()->create();
    $this->token = $this->merchant->createToken(fakeDeviceId())->plainTextToken;
});

it('creates the shop with the caller as owner and dinar as currency', function () {
    $response = $this->withToken($this->token)->postJson('/api/v1/shops', [
        'name' => 'Epicerie Amira',
        'business_type' => 'grocery',
        'city' => 'Sousse',
    ]);

    $response->assertStatus(201)->assertExactJson(['data' => [
        'id' => Shop::query()->sole()->id,
        'name' => 'Epicerie Amira',
        'business_type' => 'grocery',
        'currency' => 'TND',
        'city' => 'Sousse',
        'address' => null,
        'owner_id' => $this->merchant->id,
    ]]);
});

it('ignores a currency or owner sent by the client', function () {
    $otherMerchant = User::factory()->create();

    $this->withToken($this->token)->postJson('/api/v1/shops', [
        'name' => 'Epicerie Amira',
        'business_type' => 'grocery',
        'currency' => 'EUR',
        'owner_id' => $otherMerchant->id,
    ])
        ->assertStatus(201)
        ->assertJsonPath('data.currency', 'TND')
        ->assertJsonPath('data.owner_id', $this->merchant->id);
});

it('accepts every business type', function (string $businessType) {
    $this->withToken($this->token)->postJson('/api/v1/shops', ['name' => 'Shop', 'business_type' => $businessType])
        ->assertStatus(201)
        ->assertJsonPath('data.business_type', $businessType);
})->with(['grocery', 'hardware', 'bakery', 'clothing', 'other']);

it('refuses a second shop and leaves the first unchanged', function () {
    $firstShop = Shop::factory()->create(['owner_id' => $this->merchant->id, 'name' => 'First shop']);

    $this->withToken($this->token)->postJson('/api/v1/shops', ['name' => 'Second shop', 'business_type' => 'other'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'shop.already_exists']]);

    expect(Shop::query()->sole()->name)->toBe('First shop');
    expect(Shop::query()->sole()->id)->toBe($firstShop->id);
});

it('leaves one shop when a duplicate request wins the race', function () {
    Shop::factory()->create(['owner_id' => $this->merchant->id]);

    $this->withToken($this->token)->postJson('/api/v1/shops', ['name' => 'Retry', 'business_type' => 'grocery'])
        ->assertStatus(409);

    expect(Shop::query()->count())->toBe(1);
});

it('gives a code for each invalid field and creates no shop', function (array $body, array $fields) {
    $this->withToken($this->token)->postJson('/api/v1/shops', $body)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => $fields]]);

    expect(Shop::query()->count())->toBe(0);
})->with([
    'missing' => [[], ['name' => ['name.required'], 'business_type' => ['business_type.required']]],
    'only spaces' => [['name' => '    ', 'business_type' => 'grocery'], ['name' => ['name.required']]],
    'too long' => [['name' => str_repeat('a', 121), 'business_type' => 'grocery'], ['name' => ['name.too_long']]],
    'unknown type' => [['name' => 'Shop', 'business_type' => 'bank'], ['business_type' => ['business_type.invalid']]],
    'long city' => [['name' => 'Shop', 'business_type' => 'other', 'city' => str_repeat('a', 121)], ['city' => ['city.too_long']]],
    'long address' => [['name' => 'Shop', 'business_type' => 'other', 'address' => str_repeat('a', 256)], ['address' => ['address.too_long']]],
]);

it('refuses a missing session', function () {
    $this->postJson('/api/v1/shops', ['name' => 'Shop', 'business_type' => 'grocery'])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    expect(Shop::query()->count())->toBe(0);
});

it('keeps Arabic and accented names exactly, and trims the ends', function (string $sent, string $saved) {
    $response = $this->withToken($this->token)->postJson('/api/v1/shops', [
        'name' => $sent,
        'business_type' => 'bakery',
        'city' => 'سوسة',
        'address' => '  12 rue de la République  ',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', $saved)
        ->assertJsonPath('data.city', 'سوسة')
        ->assertJsonPath('data.address', '12 rue de la République');

    expect(Shop::query()->sole()->name)->toBe($saved);
})->with([
    ['مخبزة الأمل', 'مخبزة الأمل'],
    ['Épicerie Amira', 'Épicerie Amira'],
    ['  Boulangerie  ', 'Boulangerie'],
]);

it('accepts 120 characters of Arabic', function () {
    $name = str_repeat('م', 120);

    $this->withToken($this->token)->postJson('/api/v1/shops', ['name' => $name, 'business_type' => 'other'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', $name);
});
