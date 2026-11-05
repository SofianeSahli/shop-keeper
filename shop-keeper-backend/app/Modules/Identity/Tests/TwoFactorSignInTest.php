<?php

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function wrongCodeFor(string $code): string
{
    return $code === '111111' ? '222222' : '111111';
}

beforeEach(function () {
    Mail::fake();

    $this->user = User::factory()->withTwoFactor()->create([
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
    ]);

    $this->startPasswordSignIn = fn (?string $deviceId = null) => $this->postJson('/api/v1/auth/email/login', [
        'email' => 'amira@gmail.com',
        'password' => 'the right password',
        'device_id' => $deviceId ?? fakeDeviceId(),
    ]);

    $this->pendingToken = fn (): string => ($this->startPasswordSignIn)()->assertOk()->json('data.pending_sign_in');

    $this->latestCode = fn (): string => Mail::queued(EmailCodeMail::class)->last()->code;

    $this->sendCode = fn (string $pendingToken, string $code) => $this->postJson('/api/v1/auth/two-factor', [
        'pending_sign_in' => $pendingToken,
        'code' => $code,
    ]);

    $this->resend = fn (string $pendingToken) => $this->postJson('/api/v1/auth/two-factor/resend', [
        'pending_sign_in' => $pendingToken,
    ]);

    $this->assertPendingRefused = fn ($response) => $response
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.pending_invalid']]);
});

it('holds a password sign-in and emails a code to the account address', function () {
    $response = ($this->startPasswordSignIn)();

    $response->assertOk()
        ->assertJsonPath('data.status', 'code_required')
        ->assertJsonPath('data.expires_in_seconds', 600)
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.account');
    expect($response->json('data.pending_sign_in'))->toBeString();
    expect($this->user->tokens()->count())->toBe(0);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail) => $mail->hasTo('amira@gmail.com')
        && $mail->purpose === EmailCodePurpose::SignIn);
});

it('holds a Google sign-in until the emailed code', function () {
    config(['shop_keeper.auth_test_mode' => true]);

    $response = $this->postJson('/api/v1/auth/google', [
        'id_token' => 'test:amira@gmail.com',
        'device_id' => fakeDeviceId(),
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'code_required');
    expect($this->user->tokens()->count())->toBe(0);
    Mail::assertQueued(EmailCodeMail::class, 1);

    ($this->sendCode)($response->json('data.pending_sign_in'), '000000')
        ->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.id', $this->user->id);
});

it('sends 6 digits, and says the code lasts 10 minutes', function () {
    ($this->pendingToken)();

    expect(($this->latestCode)())->toMatch('/^\d{6}$/');

    (new EmailCodeMail('123456', EmailCodePurpose::SignIn))
        ->assertSeeInText('123456')
        ->assertSeeInText('10 minutes');
});

it('stores only hashes and never returns the code', function () {
    $response = ($this->startPasswordSignIn)();
    $code = ($this->latestCode)();
    $pendingToken = $response->json('data.pending_sign_in');
    $stored = PendingSignIn::query()->sole();

    expect($stored->token_hash)->toBe(hash('sha256', $pendingToken));
    expect($stored->code_hash)->not->toBe($code);
    expect(Hash::check($code, $stored->code_hash))->toBeTrue();
    expect($response->getContent())->not->toContain($stored->code_hash);
});

it('gives a session for the device named at the sign-in after the right code', function () {
    $deviceId = fakeDeviceId();
    $pendingToken = ($this->startPasswordSignIn)($deviceId)->json('data.pending_sign_in');

    $response = ($this->sendCode)($pendingToken, ($this->latestCode)());

    $response->assertOk()->assertJsonPath('data.status', 'signed_in');
    expect($this->user->tokens()->sole()->name)->toBe($deviceId);
    expect(PendingSignIn::query()->count())->toBe(0);
    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
});

it('answers a wrong code with code.invalid and keeps the sign-in waiting', function () {
    $pendingToken = ($this->pendingToken)();
    $code = ($this->latestCode)();

    ($this->sendCode)($pendingToken, wrongCodeFor($code))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect($this->user->tokens()->count())->toBe(0);
    ($this->sendCode)($pendingToken, $code)->assertOk();
});

it('ends the sign-in at the fifth wrong code, even for the right code', function () {
    $pendingToken = ($this->pendingToken)();
    $code = ($this->latestCode)();

    foreach (range(1, 4) as $attempt) {
        ($this->sendCode)($pendingToken, wrongCodeFor($code))->assertStatus(422);
    }

    ($this->assertPendingRefused)(($this->sendCode)($pendingToken, wrongCodeFor($code)));
    ($this->assertPendingRefused)(($this->sendCode)($pendingToken, $code));

    expect(PendingSignIn::query()->count())->toBe(0);
    expect($this->user->tokens()->count())->toBe(0);
});

it('does not block the account after 5 wrong codes', function () {
    $pendingToken = ($this->pendingToken)();
    $code = ($this->latestCode)();

    foreach (range(1, 5) as $attempt) {
        ($this->sendCode)($pendingToken, wrongCodeFor($code));
    }

    ($this->sendCode)(($this->pendingToken)(), ($this->latestCode)())->assertOk();
});

it('refuses a sign-in that is unknown, used or past 10 minutes', function () {
    ($this->assertPendingRefused)(($this->sendCode)('unknown', '123456'));

    $usedToken = ($this->pendingToken)();
    $usedCode = ($this->latestCode)();
    ($this->sendCode)($usedToken, $usedCode)->assertOk();
    ($this->assertPendingRefused)(($this->sendCode)($usedToken, $usedCode));

    $expiredToken = ($this->pendingToken)();
    $expiredCode = ($this->latestCode)();
    $this->travel(10)->minutes();
    $this->travel(1)->seconds();
    ($this->assertPendingRefused)(($this->sendCode)($expiredToken, $expiredCode));

    expect(PendingSignIn::query()->count())->toBe(0);
});

it('still accepts the code just before 10 minutes', function () {
    $pendingToken = ($this->pendingToken)();
    $code = ($this->latestCode)();

    $this->travel(9)->minutes();

    ($this->sendCode)($pendingToken, $code)->assertOk();
});

it('gives each sign-in its own code, which works only for that sign-in', function () {
    $firstToken = ($this->pendingToken)();
    $firstCode = ($this->latestCode)();
    $secondToken = ($this->pendingToken)();
    $secondCode = ($this->latestCode)();

    expect(PendingSignIn::query()->count())->toBe(2);

    ($this->sendCode)($firstToken, $secondCode)->assertStatus(422);
    ($this->sendCode)($secondToken, $firstCode)->assertStatus(422);

    ($this->sendCode)($firstToken, $firstCode)->assertOk();
    ($this->sendCode)($secondToken, $secondCode)->assertOk();
});

it('kills the previous code on a resend', function () {
    $pendingToken = ($this->pendingToken)();
    $firstCode = ($this->latestCode)();

    ($this->resend)($pendingToken)->assertStatus(202)->assertNoContent(202);
    $secondCode = ($this->latestCode)();

    Mail::assertQueued(EmailCodeMail::class, 2);
    ($this->sendCode)($pendingToken, $firstCode)->assertStatus(422);
    ($this->sendCode)($pendingToken, $secondCode)->assertOk();
});

it('restarts the 10 minutes and the wrong tries on a resend', function () {
    $pendingToken = ($this->pendingToken)();
    $firstCode = ($this->latestCode)();

    foreach (range(1, 4) as $attempt) {
        ($this->sendCode)($pendingToken, wrongCodeFor($firstCode))->assertStatus(422);
    }

    $this->travel(8)->minutes();
    ($this->resend)($pendingToken)->assertStatus(202);
    $this->travel(8)->minutes();

    $secondCode = ($this->latestCode)();
    ($this->sendCode)($pendingToken, wrongCodeFor($secondCode))->assertStatus(422);
    ($this->sendCode)($pendingToken, $secondCode)->assertOk();
});

it('sends nothing for a resend on an unknown or expired sign-in', function () {
    ($this->assertPendingRefused)(($this->resend)('unknown'));

    $expiredToken = ($this->pendingToken)();
    $this->travel(10)->minutes();
    $this->travel(1)->seconds();
    ($this->assertPendingRefused)(($this->resend)($expiredToken));

    Mail::assertQueued(EmailCodeMail::class, 1);
    expect(PendingSignIn::query()->count())->toBe(0);
});

it('limits resends per account, across sign-ins', function () {
    $firstToken = ($this->pendingToken)();
    $secondToken = ($this->pendingToken)();

    ($this->resend)($firstToken)->assertStatus(202);
    ($this->resend)($firstToken)->assertStatus(202);
    ($this->resend)($secondToken)->assertStatus(202);

    ($this->resend)($secondToken)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    $otherAccount = User::factory()->withTwoFactor()->create(['password' => 'the other password']);
    $otherToken = $this->postJson('/api/v1/auth/email/login', [
        'email' => $otherAccount->email,
        'password' => 'the other password',
        'device_id' => fakeDeviceId(),
    ])->json('data.pending_sign_in');

    ($this->resend)($otherToken)->assertStatus(202);
});

it('limits resends per network origin', function () {
    foreach (range(1, 10) as $attempt) {
        ($this->resend)('unknown')->assertStatus(401);
    }

    ($this->resend)('unknown')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});

it('limits code checks per network origin', function () {
    foreach (range(1, 30) as $attempt) {
        ($this->sendCode)('unknown', '123456')->assertStatus(401);
    }

    ($this->sendCode)('unknown', '123456')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});

it('signs in directly with no mail when the factor is off', function () {
    $this->user->forceFill(['two_factor_enabled_at' => null])->save();

    ($this->startPasswordSignIn)()->assertOk()->assertJsonPath('data.status', 'signed_in');

    Mail::assertNothingQueued();
    expect(PendingSignIn::query()->count())->toBe(0);
});

it('keeps open sessions working while a sign-in waits', function () {
    $openToken = $this->user->createToken(fakeDeviceId())->plainTextToken;

    ($this->pendingToken)();

    $this->withToken($openToken)->getJson('/api/v1/me')->assertOk();
});

it('ends a waiting sign-in when the password is reset, and keeps the factor on', function () {
    $pendingToken = ($this->pendingToken)();
    $signInCode = ($this->latestCode)();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'amira@gmail.com'])->assertStatus(202);
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'amira@gmail.com',
        'code' => ($this->latestCode)(),
        'password' => 'the new password',
    ])->assertNoContent();

    ($this->assertPendingRefused)(($this->sendCode)($pendingToken, $signInCode));
    expect($this->user->fresh()->hasTwoFactor())->toBeTrue();
});

it('gives a code for each invalid field', function () {
    $this->postJson('/api/v1/auth/two-factor', ['code' => 'abc'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pending_sign_in' => ['pending_sign_in.required'],
            'code' => ['code.format'],
        ]]]);

    $this->postJson('/api/v1/auth/two-factor/resend', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'pending_sign_in' => ['pending_sign_in.required'],
        ]]]);
});
