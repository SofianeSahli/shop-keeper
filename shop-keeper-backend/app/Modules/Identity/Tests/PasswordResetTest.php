<?php

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->account = fn (array $attributes = []): User => User::factory()->create(array_merge([
        'email' => 'amira@example.com',
        'password' => 'the old password',
    ], $attributes));

    $this->resetCode = function (): string {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);

        return Mail::queued(EmailCodeMail::class)->last()->code;
    };
});

it('answers identically for a known and an unknown address, mailing only the known one', function () {
    ($this->account)();

    $known = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com']);
    $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

    $known->assertStatus(202);
    expect($unknown->status())->toBe($known->status());
    expect($unknown->getContent())->toBe($known->getContent());

    Mail::assertQueued(EmailCodeMail::class, 1);
    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail) => $mail->hasTo('amira@example.com')
        && $mail->purpose === EmailCodePurpose::PasswordReset);
});

it('does a slow hash for an unknown address too', function () {
    Hash::spy();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])->assertStatus(202);

    Hash::shouldHaveReceived('make')->once();
});

it('sends no reset code for an unconfirmed sign-up', function () {
    ($this->account)(['email_verified_at' => null]);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);

    Mail::assertNothingQueued();
});

it('sets the new password, ends all sessions and keeps two-step sign-in', function () {
    $user = ($this->account)(['two_factor_enabled_at' => now()]);
    $phoneToken = $user->createToken(fakeDeviceId())->plainTextToken;
    $user->createToken(fakeDeviceId());

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@example.com',
        'code' => ($this->resetCode)(),
        'password' => 'the new password',
    ])->assertNoContent();

    $user->refresh();
    expect(Hash::check('the new password', $user->password))->toBeTrue();
    expect($user->tokens()->count())->toBe(0);
    expect($user->hasTwoFactor())->toBeTrue();

    Auth::forgetGuards();
    $this->withToken($phoneToken)->getJson('/api/v1/me')->assertStatus(401);
});

it('lets the new password sign in, and not the old one', function () {
    ($this->account)();

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@example.com',
        'code' => ($this->resetCode)(),
        'password' => 'the new password',
    ])->assertNoContent();

    $this->postJson('/api/v1/auth/email/login', ['email' => 'amira@example.com', 'password' => 'the old password', 'device_id' => fakeDeviceId()])
        ->assertStatus(401);
    $this->postJson('/api/v1/auth/email/login', ['email' => 'amira@example.com', 'password' => 'the new password', 'device_id' => fakeDeviceId()])
        ->assertOk();
});

it('lifts a password lock', function () {
    ($this->account)(['password_failed_attempts' => 4, 'password_locked_until' => now()->addMinutes(10)]);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@example.com',
        'code' => ($this->resetCode)(),
        'password' => 'the new password',
    ])->assertNoContent();

    $this->postJson('/api/v1/auth/email/login', ['email' => 'amira@example.com', 'password' => 'the new password', 'device_id' => fakeDeviceId()])
        ->assertOk();
});

it('gives a Google-only account a password through its mailbox', function () {
    ($this->account)(['password' => null, 'google_id' => 'sub-1']);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@example.com',
        'code' => ($this->resetCode)(),
        'password' => 'the new password',
    ])->assertNoContent();

    $this->postJson('/api/v1/auth/email/login', ['email' => 'amira@example.com', 'password' => 'the new password', 'device_id' => fakeDeviceId()])
        ->assertOk();
});

it('refuses wrong, expired, used and unknown codes', function () {
    $user = ($this->account)();
    $code = ($this->resetCode)();
    $wrongCode = $code === '111111' ? '222222' : '111111';
    $body = ['email' => 'amira@example.com', 'password' => 'the new password'];

    $this->postJson('/api/v1/auth/password/reset', [...$body, 'code' => $wrongCode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $this->postJson('/api/v1/auth/password/reset', [...$body, 'email' => 'nobody@example.com', 'code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $this->postJson('/api/v1/auth/password/reset', [...$body, 'code' => $code])->assertNoContent();

    $this->postJson('/api/v1/auth/password/reset', [...$body, 'code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $freshCode = ($this->resetCode)();
    $this->travel(16)->minutes();

    $this->postJson('/api/v1/auth/password/reset', [...$body, 'code' => $freshCode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect($user->fresh())->not->toBeNull();
});

it('does not accept a confirmation code for a reset', function () {
    config(['shop_keeper.auth_test_mode' => true]);
    $this->postJson('/api/v1/auth/email/register', [
        'name' => 'Amira',
        'email' => 'amira@example.com',
        'password' => 'long enough secret',
        'terms_version' => config('shop_keeper.terms_version'),
    ])->assertStatus(202);

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'amira@example.com', 'code' => '000000', 'password' => 'the new password'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('gives a code for each invalid field', function () {
    $this->postJson('/api/v1/auth/password/forgot', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['email' => ['email.required']]]]);

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'amira@example.com', 'code' => '123', 'password' => 'short'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'code' => ['code.format'],
            'password' => ['password.too_short'],
        ]]]);
});

it('limits forgot-password per address', function () {
    foreach (range(1, 3) as $attempt) {
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])->assertStatus(202);
    }

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
