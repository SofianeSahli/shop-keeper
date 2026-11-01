<?php

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

const GOOD_PIN = '483920';
const OTHER_GOOD_PIN = '507213';
const WRONG_PIN = '999111';

beforeEach(function () {
    $this->merchantWith = function (User $user): void {
        $this->merchant = $user;
        $this->token = $user->createToken(fakeDeviceId())->plainTextToken;
    };

    ($this->merchantWith)(User::factory()->create());
});

it('turns the PIN on, keeps devices signed in and stores only a hash', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/pin', ['pin' => GOOD_PIN])->assertNoContent();

    $user = $this->merchant->fresh();
    expect($user->pin_hash)->not->toBe(GOOD_PIN);
    expect(Hash::check(GOOD_PIN, $user->pin_hash))->toBeTrue();

    Auth::forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.pin_enabled', true);
});

it('refuses to turn on a PIN that is already on', function () {
    ($this->merchantWith)(User::factory()->withPin(GOOD_PIN)->create());

    $this->withToken($this->token)->postJson('/api/v1/me/pin', ['pin' => OTHER_GOOD_PIN])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'pin.already_enabled']]);
});

it('refuses a PIN that is not 6 digits', function (mixed $pin) {
    $this->withToken($this->token)->postJson('/api/v1/me/pin', ['pin' => $pin])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['pin' => ['pin.format']]]]);
})->with([['12345'], ['1234567'], ['12a456'], [483920], ['٤٨٣٩٢٠']]);

it('refuses a weak PIN', function (string $pin) {
    $this->withToken($this->token)->postJson('/api/v1/me/pin', ['pin' => $pin])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['pin' => ['pin.weak']]]]);

    expect($this->merchant->fresh()->hasPin())->toBeFalse();
})->with([
    ['000000'], ['111111'], ['999999'],
    ['012345'], ['123456'], ['456789'],
    ['987654'], ['543210'],
]);

it('accepts a strong PIN', function (string $pin) {
    $this->withToken($this->token)->postJson('/api/v1/me/pin', ['pin' => $pin])->assertNoContent();
})->with([['483920'], ['098765'], ['124578'], ['112233']]);

it('needs a PIN', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/pin', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['pin' => ['pin.required']]]]);
});

it('changes the PIN with the current one', function () {
    ($this->merchantWith)(User::factory()->withPin(GOOD_PIN)->create());

    $this->withToken($this->token)->putJson('/api/v1/me/pin', ['current_pin' => GOOD_PIN, 'pin' => OTHER_GOOD_PIN])
        ->assertNoContent();

    expect(Hash::check(OTHER_GOOD_PIN, $this->merchant->fresh()->pin_hash))->toBeTrue();
});

it('turns the PIN off with the current one', function () {
    ($this->merchantWith)(User::factory()->withPin(GOOD_PIN)->create());

    $this->withToken($this->token)->deleteJson('/api/v1/me/pin', ['current_pin' => GOOD_PIN])->assertNoContent();

    expect($this->merchant->fresh()->hasPin())->toBeFalse();
});

it('refuses a wrong current PIN', function () {
    ($this->merchantWith)(User::factory()->withPin(GOOD_PIN)->create());

    $this->withToken($this->token)->putJson('/api/v1/me/pin', ['current_pin' => WRONG_PIN, 'pin' => OTHER_GOOD_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'pin.invalid']]);

    $this->withToken($this->token)->deleteJson('/api/v1/me/pin', ['current_pin' => WRONG_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'pin.invalid']]);

    $user = $this->merchant->fresh();
    expect(Hash::check(GOOD_PIN, $user->pin_hash))->toBeTrue();
    expect($user->pin_failed_attempts)->toBe(2);
});

it('blocks after 5 wrong current PINs, even with the right one', function () {
    ($this->merchantWith)(User::factory()->withPin(GOOD_PIN)->create());

    foreach (range(1, 5) as $attempt) {
        $this->withToken($this->token)->deleteJson('/api/v1/me/pin', ['current_pin' => WRONG_PIN])->assertStatus(401);
    }

    $response = $this->withToken($this->token)->deleteJson('/api/v1/me/pin', ['current_pin' => GOOD_PIN]);

    $response->assertStatus(429)->assertJsonPath('error.code', 'pin.locked');
    expect($response->json('error.retry_after_seconds'))->toBeGreaterThan(0);
});

it('answers pin.not_enabled when there is no PIN to change or turn off', function () {
    $this->withToken($this->token)->putJson('/api/v1/me/pin', ['current_pin' => GOOD_PIN, 'pin' => OTHER_GOOD_PIN])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'pin.not_enabled']]);

    $this->withToken($this->token)->deleteJson('/api/v1/me/pin', ['current_pin' => GOOD_PIN])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'pin.not_enabled']]);
});

it('checks the fields of change and turn off', function () {
    $this->withToken($this->token)->putJson('/api/v1/me/pin', ['current_pin' => '12', 'pin' => '111111'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'current_pin' => ['current_pin.format'],
            'pin' => ['pin.weak'],
        ]]]);

    $this->withToken($this->token)->deleteJson('/api/v1/me/pin', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['current_pin' => ['current_pin.required']]]]);
});

it('refuses a missing session on every PIN route', function (string $method, string $path) {
    $this->json($method, $path, [])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with([
    ['POST', '/api/v1/me/pin'],
    ['PUT', '/api/v1/me/pin'],
    ['DELETE', '/api/v1/me/pin'],
    ['POST', '/api/v1/me/pin/recovery'],
]);
