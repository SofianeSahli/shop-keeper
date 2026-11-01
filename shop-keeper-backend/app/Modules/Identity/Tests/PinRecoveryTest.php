<?php

use App\Modules\Identity\Google\TestModeVerifier;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

const FORGOTTEN_PIN = '483920';
const NEW_PIN = '507213';

beforeEach(function () {
    config(['shop_keeper.auth_test_mode' => true]);

    $this->user = User::factory()->withPin(FORGOTTEN_PIN)->create([
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
        'google_id' => app(TestModeVerifier::class)->verify('test:amira@gmail.com')->sub,
    ]);
    $this->currentToken = $this->user->createToken(fakeDeviceId())->plainTextToken;
    $this->otherToken = $this->user->createToken(fakeDeviceId())->plainTextToken;

    $this->assertRecovered = function (): void {
        expect(Hash::check(NEW_PIN, $this->user->fresh()->pin_hash))->toBeTrue();

        Auth::forgetGuards();
        $this->withToken($this->otherToken)->getJson('/api/v1/me')->assertStatus(401);

        Auth::forgetGuards();
        $this->withToken($this->currentToken)->getJson('/api/v1/me')->assertOk();
    };
});

it('sets a new PIN with the password and ends other devices', function () {
    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'password' => 'the right password',
    ])->assertNoContent();

    ($this->assertRecovered)();
});

it('sets a new PIN with a fresh Google sign-in and ends other devices', function () {
    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'id_token' => 'test:amira@gmail.com',
    ])->assertNoContent();

    ($this->assertRecovered)();
});

it('lifts a PIN block', function () {
    $this->user->forceFill(['pin_locked_until' => now()->addMinutes(10), 'pin_failed_attempts' => 3])->save();

    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'password' => 'the right password',
    ])->assertNoContent();

    $user = $this->user->fresh();
    expect($user->pin_locked_until)->toBeNull();
    expect($user->pin_failed_attempts)->toBe(0);
});

it('refuses a wrong password and counts it', function () {
    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'password' => 'a wrong password',
    ])->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.invalid_credentials']]);

    $user = $this->user->fresh();
    expect(Hash::check(FORGOTTEN_PIN, $user->pin_hash))->toBeTrue();
    expect($user->password_failed_attempts)->toBe(1);
    expect($user->tokens()->count())->toBe(2);
});

it('refuses another person Google identity', function () {
    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'id_token' => 'test:stranger@gmail.com',
    ])->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);

    expect(Hash::check(FORGOTTEN_PIN, $this->user->fresh()->pin_hash))->toBeTrue();
});

it('refuses Google proof on an account with no Google link', function () {
    $this->user->forceFill(['google_id' => null])->save();

    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', [
        'pin' => NEW_PIN,
        'id_token' => 'test:amira@gmail.com',
    ])->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);
});

it('needs a proof and a valid PIN', function () {
    $this->withToken($this->currentToken)->postJson('/api/v1/me/pin/recovery', ['pin' => '111111'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pin' => ['pin.weak'],
            'id_token' => ['id_token.required'],
        ]]]);
});

it('lets the operator turn off the PIN and end all sessions', function () {
    $this->artisan('merchant:reset-pin', ['email' => 'amira@gmail.com'])->assertSuccessful();

    $user = $this->user->fresh();
    expect($user->hasPin())->toBeFalse();
    expect($user->pin_locked_until)->toBeNull();
    expect($user->tokens()->count())->toBe(0);

    $this->withToken($this->currentToken)->getJson('/api/v1/me')->assertStatus(401);
});

it('fails the operator command on an unknown address, in any spelling', function () {
    $this->artisan('merchant:reset-pin', ['email' => 'nobody@gmail.com'])->assertFailed();

    expect($this->user->fresh()->hasPin())->toBeTrue();

    $this->artisan('merchant:reset-pin', ['email' => 'AMIRA@gmail.com'])->assertSuccessful();

    expect($this->user->fresh()->hasPin())->toBeFalse();
});

it('offers no API route for the operator reset', function () {
    $operatorRoutes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri) => str_contains($uri, 'reset-pin') || str_contains($uri, 'operator'));

    expect($operatorRoutes)->toBeEmpty();
});
