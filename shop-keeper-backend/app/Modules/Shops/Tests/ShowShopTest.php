<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;

beforeEach(function () {
    $this->merchant = User::factory()->create();
    $this->shop = Shop::factory()->create(['owner_id' => $this->merchant->id, 'name' => 'Epicerie Amira']);
    $this->token = $this->merchant->createToken(fakeDeviceId())->plainTextToken;
});

it('lets the owner read their shop', function () {
    $this->withToken($this->token)->getJson("/api/v1/shops/{$this->shop->id}")
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $this->shop->id,
            'name' => 'Epicerie Amira',
            'business_type' => $this->shop->business_type,
            'currency' => 'TND',
            'city' => $this->shop->city,
            'address' => $this->shop->address,
            'owner_id' => $this->merchant->id,
        ]]);
});

it('refuses another merchant and an unknown id the same way, with no shop data', function () {
    $otherMerchant = User::factory()->create();
    $otherToken = $otherMerchant->createToken(fakeDeviceId())->plainTextToken;
    Shop::factory()->create(['owner_id' => $otherMerchant->id]);

    $foreign = $this->withToken($otherToken)->getJson("/api/v1/shops/{$this->shop->id}");
    $unknown = $this->withToken($otherToken)->getJson('/api/v1/shops/999999');
    $notAnId = $this->withToken($otherToken)->getJson('/api/v1/shops/not-an-id');

    $foreign->assertStatus(403)->assertExactJson(['error' => ['code' => 'shop.forbidden']]);
    expect($foreign->getContent())->not->toContain('Epicerie');
    expect($unknown->status())->toBe($foreign->status());
    expect($unknown->getContent())->toBe($foreign->getContent());
    expect($notAnId->getContent())->toBe($foreign->getContent());
});

it('refuses a merchant with no shop', function () {
    $newMerchant = User::factory()->create();
    $token = $newMerchant->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/shops/{$this->shop->id}")
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.forbidden']]);
});

it('refuses a missing session', function () {
    $this->getJson("/api/v1/shops/{$this->shop->id}")
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
