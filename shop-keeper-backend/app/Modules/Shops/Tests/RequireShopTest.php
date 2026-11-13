<?php

use App\Modules\Identity\Models\User;
use App\Modules\Shops\Models\Shop;
use App\Shared\CurrentShop;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['api', 'auth:sanctum', 'shop'])
        ->get('api/v1/test/current-shop', fn (CurrentShop $currentShop) => response()->json(['data' => $currentShop->shopId]));
    $this->merchant = User::factory()->create();
    $this->token = $this->merchant->createToken(fakeDeviceId())->plainTextToken;
});

it('gives the request the shop of the signed-in merchant', function () {
    $shop = Shop::factory()->create(['owner_id' => $this->merchant->id]);
    Shop::factory()->create(['owner_id' => User::factory()->create()->id]);

    $this->withToken($this->token)->getJson('/api/v1/test/current-shop')
        ->assertOk()
        ->assertExactJson(['data' => $shop->id]);
});

it('refuses a merchant with no shop', function () {
    $this->withToken($this->token)->getJson('/api/v1/test/current-shop')
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});

it('refuses a request with no session', function () {
    $this->getJson('/api/v1/test/current-shop')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});
