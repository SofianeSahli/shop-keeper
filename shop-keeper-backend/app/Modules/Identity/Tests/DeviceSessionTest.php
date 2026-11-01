<?php

use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    config(['shop_keeper.auth_test_mode' => true]);

    $this->signIn = fn (string $deviceId, ?string $termsVersion = null): string => $this
        ->postJson('/api/v1/auth/google', array_filter([
            'id_token' => 'test:amira@gmail.com',
            'device_id' => $deviceId,
            'terms_version' => $termsVersion,
        ]))
        ->assertOk()
        ->json('data.token');

    $this->assertSessionWorks = function (string $token): void {
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    };

    $this->assertSessionStopped = function (string $token): void {
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    };

    ($this->signIn)(fakeDeviceId(), config('shop_keeper.terms_version'));
});

it('does not ask for the terms again on a new device', function () {
    $token = ($this->signIn)(fakeDeviceId());

    ($this->assertSessionWorks)($token);
});

it('replaces the session of a device that signs in again', function () {
    $deviceId = fakeDeviceId();

    $firstToken = ($this->signIn)($deviceId);
    $secondToken = ($this->signIn)($deviceId);

    ($this->assertSessionStopped)($firstToken);
    ($this->assertSessionWorks)($secondToken);
});

it('signs out of the current device only', function () {
    $phoneToken = ($this->signIn)(fakeDeviceId());
    $tabletToken = ($this->signIn)(fakeDeviceId());

    $this->withToken($phoneToken)->postJson('/api/v1/auth/logout')->assertNoContent();

    ($this->assertSessionStopped)($phoneToken);
    ($this->assertSessionWorks)($tabletToken);
});

it('refuses to sign out with no session', function () {
    $this->postJson('/api/v1/auth/logout')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('limits sign-in attempts per network origin', function () {
    foreach (range(1, 28) as $attempt) {
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => fakeDeviceId()]);
    }

    $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => fakeDeviceId()])
        ->assertStatus(401);

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'device_id' => fakeDeviceId()]);

    $response->assertStatus(429)->assertJsonPath('error.code', 'request.throttled');
    expect($response->json('error.retry_after_seconds'))->toBeGreaterThan(0);
});
