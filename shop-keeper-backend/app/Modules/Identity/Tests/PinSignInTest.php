<?php

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;

const ACCOUNT_PIN = '483920';
const NOT_THE_PIN = '999111';

beforeEach(function () {
    config(['shop_keeper.auth_test_mode' => true]);

    $this->user = User::factory()->withPin(ACCOUNT_PIN)->create([
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
    ]);

    $this->startPasswordSignIn = fn (?string $deviceId = null) => $this->postJson('/api/v1/auth/email/login', [
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
        'device_id' => $deviceId ?? fakeDeviceId(),
    ]);

    $this->pendingToken = fn (): string => ($this->startPasswordSignIn)()->assertOk()->json('data.pending_sign_in');
});

it('holds a password sign-in until the PIN', function () {
    $response = ($this->startPasswordSignIn)();

    $response->assertOk()
        ->assertJsonPath('data.status', 'pin_required')
        ->assertJsonPath('data.expires_in_seconds', 300)
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.account');
    expect($response->json('data.pending_sign_in'))->toBeString();
    expect($this->user->tokens()->count())->toBe(0);
});

it('holds a Google sign-in until the PIN', function () {
    $response = $this->postJson('/api/v1/auth/google', [
        'id_token' => 'test:amira@gmail.com',
        'device_id' => fakeDeviceId(),
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'pin_required');
    expect($this->user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $response->json('data.pending_sign_in'), 'pin' => ACCOUNT_PIN])
        ->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $this->user->id);
});

it('stores only a hash of the pending token', function () {
    $pendingToken = ($this->pendingToken)();

    expect(PendingSignIn::query()->sole()->token_hash)
        ->toBe(hash('sha256', $pendingToken))
        ->not->toBe($pendingToken);
});

it('gives a session for the device named at the sign-in after the right PIN', function () {
    $deviceId = fakeDeviceId();
    $pendingToken = ($this->startPasswordSignIn)($deviceId)->json('data.pending_sign_in');

    $response = $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => ACCOUNT_PIN]);

    $response->assertOk()->assertJsonPath('data.status', 'signed_in');
    expect($this->user->tokens()->sole()->name)->toBe($deviceId);
    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
});

it('refuses a pending sign-in that is unknown, used or past 5 minutes', function () {
    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => 'unknown', 'pin' => ACCOUNT_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);

    $usedToken = ($this->pendingToken)();
    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $usedToken, 'pin' => ACCOUNT_PIN])->assertOk();
    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $usedToken, 'pin' => ACCOUNT_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);

    $expiredToken = ($this->pendingToken)();
    $this->travel(5)->minutes();
    $this->travel(1)->seconds();
    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $expiredToken, 'pin' => ACCOUNT_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);

    expect(PendingSignIn::query()->count())->toBe(0);
});

it('answers a wrong PIN with pin.invalid and keeps the pending sign-in', function () {
    $pendingToken = ($this->pendingToken)();

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => NOT_THE_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'pin.invalid']]);

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => ACCOUNT_PIN])->assertOk();
});

it('blocks after 5 wrong PINs, even with the right one', function () {
    $pendingToken = ($this->pendingToken)();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => NOT_THE_PIN])->assertStatus(401);
    }

    $response = $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => ACCOUNT_PIN]);

    $response->assertStatus(429)->assertJsonPath('error.code', 'pin.locked');
    expect($response->json('error.retry_after_seconds'))->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(900);
    expect($this->user->tokens()->count())->toBe(0);
});

it('resets the count on a right PIN', function () {
    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => ($this->pendingToken)(), 'pin' => NOT_THE_PIN])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => ($this->pendingToken)(), 'pin' => ACCOUNT_PIN])->assertOk();

    expect($this->user->fresh()->pin_failed_attempts)->toBe(0);
});

it('ends the block after 15 minutes', function () {
    $this->user->update(['pin_failed_attempts' => 0]);
    $this->user->forceFill(['pin_locked_until' => now()->addMinutes(15)])->save();

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => ($this->pendingToken)(), 'pin' => ACCOUNT_PIN])->assertOk();
});

it('keeps open sessions working during a block', function () {
    $openToken = $this->user->createToken(fakeDeviceId())->plainTextToken;
    $this->user->forceFill(['pin_locked_until' => now()->addMinutes(15)])->save();

    $this->withToken($openToken)->getJson('/api/v1/me')->assertOk();
});

it('ends a pending sign-in when the password is reset', function () {
    Mail::fake();
    $pendingToken = ($this->pendingToken)();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@gmail.com'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@gmail.com',
        'code' => Mail::queued(EmailCodeMail::class)->last()->code,
        'password' => 'the new password',
    ])->assertNoContent();

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => ACCOUNT_PIN])
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);
});

it('gives a code for each invalid field', function () {
    $this->postJson('/api/v1/auth/pin', ['pin' => 'abc'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pending_sign_in' => ['pending_sign_in.required'],
            'pin' => ['pin.format'],
        ]]]);
});

it('limits PIN attempts per network origin', function () {
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => 'unknown', 'pin' => NOT_THE_PIN])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => 'unknown', 'pin' => NOT_THE_PIN])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
