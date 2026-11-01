<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;

it('reads the signed-in account', function () {
    $user = User::factory()->create(['phone' => '+21620123456']);
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+21620123456',
            'pin_enabled' => false,
            'shop' => null,
        ]]);
});

it('includes the merchant shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create(['owner_id' => $user->id, 'name' => 'Epicerie Amira', 'city' => 'Sousse', 'address' => null]);
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.shop', [
            'id' => $shop->id,
            'name' => 'Epicerie Amira',
            'business_type' => $shop->business_type,
            'currency' => 'TND',
            'city' => 'Sousse',
            'address' => null,
            'owner_id' => $user->id,
        ]);
});

it('shows only the merchant own shop', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Shop::factory()->create(['owner_id' => $otherUser->id]);
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.shop', null);
});

it('says whether a PIN is on and never returns a hash', function () {
    $user = User::factory()->withPin('483920')->create();
    $token = $user->createToken(fakeDeviceId())->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/me');

    $response->assertOk()->assertJsonPath('data.pin_enabled', true);
    expect($response->getContent())
        ->not->toContain($user->pin_hash)
        ->not->toContain($user->password)
        ->not->toContain('pin_hash');
});

it('refuses a missing or invalid session', function () {
    $this->getJson('/api/v1/me')->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    $this->withToken('1|not-a-real-token')->getJson('/api/v1/me')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
