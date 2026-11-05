<?php

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function codeOtherThan(string $code): string
{
    return $code === '111111' ? '222222' : '111111';
}

beforeEach(function () {
    Mail::fake();

    $this->merchantWith = function (User $user): void {
        $this->merchant = $user;
        $this->token = $user->createToken(fakeDeviceId())->plainTextToken;
    };

    $this->askForDisableCode = function (): string {
        $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code')->assertStatus(202);

        return Mail::queued(EmailCodeMail::class)->last()->code;
    };

    ($this->merchantWith)(User::factory()->create());
});

it('is off by default', function () {
    $this->withToken($this->token)->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', false);
});

it('turns on and keeps devices signed in', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/two-factor')->assertNoContent();

    expect($this->merchant->fresh()->hasTwoFactor())->toBeTrue();

    Auth::forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.two_factor_enabled', true);
});

it('refuses to turn on twice', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    $this->withToken($this->token)->postJson('/api/v1/me/two-factor')
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.already_enabled']]);
});

it('emails a disable code to the account address', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code')
        ->assertStatus(202)
        ->assertNoContent(202);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail) => $mail->hasTo($this->merchant->email)
        && $mail->purpose === EmailCodePurpose::TwoFactorDisable);

    $stored = EmailCode::query()->sole();
    expect($stored->purpose)->toBe(EmailCodePurpose::TwoFactorDisable);
    expect(Hash::check(Mail::queued(EmailCodeMail::class)->last()->code, $stored->code_hash))->toBeTrue();
});

it('refuses a disable code when the factor is off', function () {
    $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code')
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.not_enabled']]);

    Mail::assertNothingQueued();
});

it('turns off with the right code and ends no session', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());
    $otherToken = $this->merchant->createToken(fakeDeviceId())->plainTextToken;

    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => ($this->askForDisableCode)()])
        ->assertNoContent();

    expect($this->merchant->fresh()->hasTwoFactor())->toBeFalse();
    expect(EmailCode::query()->count())->toBe(0);

    Auth::forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk();
    Auth::forgetGuards();
    $this->withToken($otherToken)->getJson('/api/v1/me')->assertOk();
});

it('stays on with a wrong, expired, used or missing code', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());
    $refuse = fn (string $code) => $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $refuse('123456');

    $code = ($this->askForDisableCode)();
    $refuse(codeOtherThan($code));

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();
    $refuse($code);

    expect($this->merchant->fresh()->hasTwoFactor())->toBeTrue();
});

it('refuses a code that was already used', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());
    $code = ($this->askForDisableCode)();

    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $code])->assertNoContent();
    $this->merchant->fresh()->forceFill(['two_factor_enabled_at' => now()])->save();

    Auth::forgetGuards();
    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('kills the disable code after 5 wrong tries', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());
    $code = ($this->askForDisableCode)();

    foreach (range(1, 5) as $attempt) {
        $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => codeOtherThan($code)])
            ->assertStatus(422);
    }

    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $code])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect($this->merchant->fresh()->hasTwoFactor())->toBeTrue();
});

it('does not take a code of another purpose or another account', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    $this->postJson('/api/v1/auth/password/forgot', ['email' => $this->merchant->email])->assertStatus(202);
    $resetCode = Mail::queued(EmailCodeMail::class)->last()->code;

    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $resetCode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    $disableCode = ($this->askForDisableCode)();
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    Auth::forgetGuards();
    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => $disableCode])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('refuses to turn off when the factor is off', function () {
    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', ['code' => '123456'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'two_factor.not_enabled']]);
});

it('gives a code for an invalid field', function (array $body, string $fieldCode) {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    $this->withToken($this->token)->deleteJson('/api/v1/me/two-factor', $body)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['code' => [$fieldCode]]]]);
})->with([
    [[], 'code.required'],
    [['code' => '12345'], 'code.format'],
    [['code' => 123456], 'code.format'],
]);

it('limits disable codes per account and leaves other accounts alone', function () {
    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    foreach (range(1, 3) as $attempt) {
        $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code')->assertStatus(202);
    }

    $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code', ['email' => 'any@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    ($this->merchantWith)(User::factory()->withTwoFactor()->create());

    Auth::forgetGuards();
    $this->withToken($this->token)->postJson('/api/v1/me/two-factor/disable-code')->assertStatus(202);
});

it('needs a session', function (string $method, string $path) {
    $this->json($method, $path)
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
})->with([
    ['POST', '/api/v1/me/two-factor'],
    ['POST', '/api/v1/me/two-factor/disable-code'],
    ['DELETE', '/api/v1/me/two-factor'],
]);
