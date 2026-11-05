<?php

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function registerBody(array $overrides = []): array
{
    return array_merge([
        'name' => 'Amira',
        'email' => 'amira@example.com',
        'password' => 'long enough secret',
        'terms_version' => config('shop_keeper.terms_version'),
    ], $overrides);
}

function lastEmailedCode(): string
{
    return Mail::queued(EmailCodeMail::class)->last()->code;
}

beforeEach(function () {
    Mail::fake();
});

it('registers with a queued code and no session', function () {
    $response = $this->postJson('/api/v1/auth/email/register', registerBody(['phone' => '+216 20 123 456']));

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');

    $user = User::query()->sole();
    expect($user->hasConfirmedEmail())->toBeFalse();
    expect($user->phone)->toBe('+21620123456');
    expect($user->terms_version)->toBe(config('shop_keeper.terms_version'));
    expect($user->terms_accepted_at)->not->toBeNull();
    expect($user->tokens()->count())->toBe(0);

    Mail::assertQueued(EmailCodeMail::class, fn (EmailCodeMail $mail) => $mail->hasTo('amira@example.com')
        && $mail->purpose === EmailCodePurpose::EmailConfirmation);
});

it('renders a mail holding the code, and encrypts it on the queue', function () {
    $mail = new EmailCodeMail('123456', EmailCodePurpose::PasswordReset);

    $mail->assertSeeInText('123456');
    expect($mail)->toBeInstanceOf(ShouldBeEncrypted::class);
    expect($mail)->toBeInstanceOf(ShouldQueue::class);
});

it('stores only a hash of the code', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);

    $emailCode = EmailCode::query()->sole();
    expect($emailCode->code_hash)->not->toBe(lastEmailedCode());
    expect(Hash::check(lastEmailedCode(), $emailCode->code_hash))->toBeTrue();
});

it('replaces the name, password and code of an unconfirmed sign-up', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);
    $firstHash = EmailCode::query()->sole()->code_hash;

    $this->postJson('/api/v1/auth/email/register', registerBody(['name' => 'Real owner', 'password' => 'the real password']))
        ->assertStatus(202);

    $user = User::query()->sole();
    expect($user->name)->toBe('Real owner');
    expect(Hash::check('the real password', $user->password))->toBeTrue();
    expect(EmailCode::query()->sole()->code_hash)->not->toBe($firstHash);
});

it('refuses an address with a confirmed account and leaves it unchanged', function () {
    $user = User::factory()->create(['email' => 'amira@example.com', 'name' => 'Amira']);

    $this->postJson('/api/v1/auth/email/register', registerBody(['name' => 'Stranger']))
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'email.taken']]);

    expect($user->fresh()->name)->toBe('Amira');
    Mail::assertNothingQueued();
});

it('confirms the address and gives a session', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());

    $response = $this->postJson('/api/v1/auth/email/confirm', [
        'email' => 'amira@example.com',
        'code' => lastEmailedCode(),
        'device_id' => fakeDeviceId(),
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.email', 'amira@example.com');
    expect(User::query()->sole()->hasConfirmedEmail())->toBeTrue();
    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
});

it('uses 000000 in test mode', function () {
    config(['shop_keeper.auth_test_mode' => true]);
    $this->postJson('/api/v1/auth/email/register', registerBody());

    $this->postJson('/api/v1/auth/email/confirm', [
        'email' => 'amira@example.com',
        'code' => '000000',
        'device_id' => fakeDeviceId(),
    ])->assertOk();
});

it('refuses a wrong code', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());
    $wrongCode = lastEmailedCode() === '111111' ? '222222' : '111111';

    $this->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => $wrongCode, 'device_id' => fakeDeviceId()])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);

    expect(User::query()->sole()->hasConfirmedEmail())->toBeFalse();
});

it('refuses an expired code', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());

    $this->travel(15)->minutes();
    $this->travel(1)->seconds();

    $this->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => lastEmailedCode(), 'device_id' => fakeDeviceId()])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('refuses a used code', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());
    $body = ['email' => 'amira@example.com', 'code' => lastEmailedCode(), 'device_id' => fakeDeviceId()];

    $this->postJson('/api/v1/auth/email/confirm', $body)->assertOk();

    $this->postJson('/api/v1/auth/email/confirm', $body)
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('refuses an unknown address the same way', function () {
    $this->postJson('/api/v1/auth/email/confirm', ['email' => 'nobody@example.com', 'code' => '123456', 'device_id' => fakeDeviceId()])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('kills a code after 5 wrong tries', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());
    $rightCode = lastEmailedCode();
    $wrongCode = $rightCode === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => $wrongCode, 'device_id' => fakeDeviceId()])
            ->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => $rightCode, 'device_id' => fakeDeviceId()])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'code.invalid']]);
});

it('resends a new code for an unconfirmed sign-up', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody());
    $firstHash = EmailCode::query()->sole()->code_hash;

    $response = $this->postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com']);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
    expect(EmailCode::query()->sole()->code_hash)->not->toBe($firstHash);
    Mail::assertQueued(EmailCodeMail::class, 2);
});

it('gives the same answer to a resend for any address and sends nothing', function (Closure $prepare, string $email) {
    $prepare();

    $response = $this->postJson('/api/v1/auth/email/resend', ['email' => $email]);

    $response->assertStatus(202);
    expect($response->getContent())->toBe('');
    Mail::assertNothingQueued();
})->with([
    'unknown' => [fn () => null, 'nobody@example.com'],
    'confirmed' => [fn () => User::factory()->create(['email' => 'amira@example.com']), 'amira@example.com'],
]);

it('compares addresses in lowercase', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody(['email' => 'Amira@Example.COM']))->assertStatus(202);

    expect(User::query()->sole()->email)->toBe('amira@example.com');

    $this->postJson('/api/v1/auth/email/confirm', [
        'email' => 'AMIRA@example.com',
        'code' => lastEmailedCode(),
        'device_id' => fakeDeviceId(),
    ])->assertOk();

    $this->postJson('/api/v1/auth/email/register', registerBody(['email' => 'AMIRA@EXAMPLE.COM']))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'email.taken');
});

it('refuses a password under 8 characters', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody(['password' => 'short']))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['password' => ['password.too_short']]]]);
});

it('accepts 72 bytes and refuses more', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody(['password' => str_repeat('a', 72)]))->assertStatus(202);

    $this->postJson('/api/v1/auth/email/register', registerBody([
        'email' => 'other@example.com',
        'password' => str_repeat('é', 37),
    ]))->assertStatus(422)->assertJsonPath('error.fields.password', ['password.too_long']);
});

it('gives a code for each invalid field', function () {
    $this->postJson('/api/v1/auth/email/register', [
        'name' => '   ',
        'email' => 'not-an-address',
        'password' => 'long enough secret',
        'terms_version' => '2020-01',
        'phone' => '20123456',
    ])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'name' => ['name.required'],
            'email' => ['email.invalid'],
            'terms_version' => ['terms_version.outdated'],
            'phone' => ['phone.invalid'],
        ]]]);

    expect(User::query()->count())->toBe(0);
});

it('needs the required fields', function () {
    $this->postJson('/api/v1/auth/email/register', [])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'name' => ['name.required'],
            'email' => ['email.required'],
            'password' => ['password.required'],
            'terms_version' => ['terms_version.required'],
        ]]]);

    $this->postJson('/api/v1/auth/email/confirm', ['email' => 'amira@example.com', 'code' => '12ab', 'device_id' => 'nope'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => [
            'code' => ['code.format'],
            'device_id' => ['device_id.invalid'],
        ]]]);
});

it('needs an address to resend a code', function () {
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'not-an-address'])
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'validation.failed', 'fields' => ['email' => ['email.invalid']]]]);
});

it('answers fields of the wrong type with codes, never with text', function () {
    $response = $this->postJson('/api/v1/auth/email/register', [
        'name' => ['a'],
        'email' => ['b'],
        'password' => ['c'],
        'terms_version' => ['d'],
        'phone' => ['e'],
    ]);

    $response->assertStatus(422);

    foreach ($response->json('error.fields') as $field => $codes) {
        foreach ($codes as $code) {
            expect($code)->toStartWith("{$field}.")->not->toContain(' ');
        }
    }
});

it('limits email sending per address, across sign-up and resend', function () {
    $this->postJson('/api/v1/auth/email/register', registerBody())->assertStatus(202);
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com'])->assertStatus(202);
    $this->postJson('/api/v1/auth/email/resend', ['email' => 'AMIRA@example.com'])->assertStatus(202);

    $this->postJson('/api/v1/auth/email/resend', ['email' => 'amira@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');

    $this->postJson('/api/v1/auth/email/resend', ['email' => 'other@example.com'])->assertStatus(202);
});

it('limits email sending per network origin', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/auth/email/resend', ['email' => "person{$attempt}@example.com"])->assertStatus(202);
    }

    $this->postJson('/api/v1/auth/email/resend', ['email' => 'person11@example.com'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'request.throttled');
});
