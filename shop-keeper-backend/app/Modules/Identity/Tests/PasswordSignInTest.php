<?php

use App\Modules\Identity\Actions\CheckPassword;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

function passwordLoginBody(string $password, string $email = 'amira@example.com'): array
{
    return ['email' => $email, 'password' => $password, 'device_id' => fakeDeviceId()];
}

beforeEach(function () {
    $this->account = fn (array $attributes = []): User => User::factory()->create(array_merge([
        'email' => 'amira@example.com',
        'password' => 'the right password',
    ], $attributes));
});

it('gives a session for the right password', function () {
    $user = ($this->account)();

    $response = $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'));

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $user->id);

    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
});

it('compares the address in lowercase', function () {
    ($this->account)();

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password', 'AMIRA@Example.com'))->assertOk();
});

it('keeps an unconfirmed address out, even with the right password', function () {
    ($this->account)(['email_verified_at' => null]);

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))
        ->assertStatus(403)
        ->assertExactJson(['error' => ['code' => 'auth.email_unconfirmed']]);
});

it('answers a wrong password and an unknown address identically', function () {
    ($this->account)();

    $wrongPassword = $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    $unknownAddress = $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password', 'nobody@example.com'));

    $wrongPassword->assertStatus(401)->assertExactJson(['error' => ['code' => 'auth.invalid_credentials']]);
    expect($unknownAddress->status())->toBe($wrongPassword->status());
    expect($unknownAddress->getContent())->toBe($wrongPassword->getContent());
});

it('checks the dummy hash for an unknown address', function () {
    Hash::spy();

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password', 'nobody@example.com'))
        ->assertStatus(401);

    Hash::shouldHaveReceived('check')->once()->with('a wrong password', CheckPassword::DUMMY_HASH);
});

it('never opens a Google-only account by password', function () {
    ($this->account)(['password' => null, 'google_id' => 'sub-1']);

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('any password at all'))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.invalid_credentials']]);
});

it('locks after 5 wrong passwords, even with the right one', function () {
    ($this->account)();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    $response = $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'));

    $response->assertStatus(429)->assertJsonPath('error.code', 'auth.password_locked');
    expect($response->json('error.retry_after_seconds'))->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(900);
});

it('resets the count on a success', function () {
    ($this->account)();

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();
});

it('ends the lock after 15 minutes', function () {
    ($this->account)();

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    }

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('the right password'))->assertOk();
});

it('does not stack another lock from attempts made during one', function () {
    $user = ($this->account)();

    foreach (range(1, 8) as $attempt) {
        $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'));
    }

    $lockedUntil = $user->fresh()->password_locked_until;
    $this->travel(5)->minutes();
    $this->postJson('/api/v1/auth/email/login', passwordLoginBody('a wrong password'))->assertStatus(429);

    expect($user->fresh()->password_locked_until->equalTo($lockedUntil))->toBeTrue();
});

it('gives a code for each invalid field', function () {
    $this->postJson('/api/v1/auth/email/login', ['email' => 'not-an-address', 'device_id' => 'nope'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'email' => ['email.invalid'],
            'password' => ['password.required'],
            'device_id' => ['device_id.invalid'],
        ]]]);
});
