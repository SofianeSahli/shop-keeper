<?php

use App\Modules\Identity\Google\GoogleIdentity;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;

function fakeGoogleIdentity(GoogleIdentity $identity): void
{
    app()->instance(GoogleIdentityChecker::class, new class($identity) implements GoogleIdentityChecker
    {
        public function __construct(private GoogleIdentity $identity) {}

        public function verify(string $idToken): GoogleIdentity
        {
            return $this->identity;
        }
    });
}

function fakeGoogleFailure(string $code, int $status): void
{
    app()->instance(GoogleIdentityChecker::class, new class($code, $status) implements GoogleIdentityChecker
    {
        public function __construct(private string $code, private int $status) {}

        public function verify(string $idToken): GoogleIdentity
        {
            throw new ApiException($this->code, $this->status);
        }
    });
}

function googleSignInBody(array $overrides = []): array
{
    return array_merge([
        'id_token' => 'signed-by-google',
        'device_id' => fakeDeviceId(),
        'terms_version' => config('shop_keeper.terms_version'),
    ], $overrides);
}

it('creates the account with the terms at a first sign-in', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));
    $this->freezeSecond();

    $response = $this->postJson('/api/v1/auth/google', googleSignInBody());

    $response->assertOk()
        ->assertJsonPath('data.status', 'signed_in')
        ->assertJsonPath('data.account.name', 'Amira')
        ->assertJsonPath('data.account.email', 'amira@gmail.com')
        ->assertJsonPath('data.account.phone', null)
        ->assertJsonPath('data.account.shop', null);

    $user = User::query()->where('google_id', 'sub-1')->firstOrFail();
    expect($user->terms_version)->toBe(config('shop_keeper.terms_version'));
    expect($user->terms_accepted_at->equalTo(now()))->toBeTrue();
    expect($user->hasConfirmedEmail())->toBeTrue();
    expect($user->password)->toBeNull();

    $this->withToken($response->json('data.token'))->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('saves the phone in its standard form', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['phone' => '00216 20 123 456']))
        ->assertOk()
        ->assertJsonPath('data.account.phone', '+21620123456');
});

it('needs the terms at a first sign-in', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertStatus(422)
        ->assertExactJson(['error' => ['code' => 'auth.terms_required']]);

    expect(User::query()->count())->toBe(0);
});

it('refuses an outdated terms version', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => '2020-01']))
        ->assertStatus(422)
        ->assertExactJson(['error' => [
            'code' => 'validation.failed',
            'fields' => ['terms_version' => ['terms_version.outdated']],
        ]]);
});

it('gives a code for each invalid field and creates no account', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['phone' => '20123456', 'device_id' => 'not-a-uuid']))
        ->assertStatus(422)
        ->assertExactJson(['error' => [
            'code' => 'validation.failed',
            'fields' => ['device_id' => ['device_id.invalid'], 'phone' => ['phone.invalid']],
        ]]);

    expect(User::query()->count())->toBe(0);
});

it('needs an identity and a device', function () {
    $this->postJson('/api/v1/auth/google', [])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.id_token', ['id_token.required'])
        ->assertJsonPath('error.fields.device_id', ['device_id.required']);
});

it('answers a refused identity with auth.google_invalid', function () {
    fakeGoogleFailure('auth.google_invalid', 401);

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);

    expect(User::query()->count())->toBe(0);
});

it('answers unreachable keys with auth.google_unavailable', function () {
    fakeGoogleFailure('auth.google_unavailable', 503);

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(503)
        ->assertExactJson(['error' => ['code' => 'auth.google_unavailable']]);
});

it('refuses a match on an address Google is not authoritative for', function (Closure $existingAccount) {
    $existing = $existingAccount();
    fakeGoogleIdentity(new GoogleIdentity('sub-1', $existing->email, 'Stranger'));

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'auth.google_email_conflict']]);

    expect($existing->fresh())->not->toBeNull();
    expect($existing->fresh()->google_id)->toBeNull();
})->with([
    'confirmed' => [fn () => User::factory()->create(['email' => 'amira@shop.tn'])],
    'unconfirmed' => [fn () => User::factory()->unverified()->create(['email' => 'amira@shop.tn'])],
]);

it('refuses an account already linked to another Google identity', function () {
    $user = User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-other']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'auth.google_email_conflict']]);

    expect($user->fresh()->google_id)->toBe('sub-other');
});

it('links Google to a confirmed account on a Gmail address', function () {
    $user = User::factory()->create(['email' => 'amira@gmail.com']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertOk()
        ->assertJsonPath('data.account.id', $user->id);

    expect($user->fresh()->google_id)->toBe('sub-1');
    expect(User::query()->count())->toBe(1);
});

it('links Google to a confirmed account on a hosted domain', function () {
    $user = User::factory()->create(['email' => 'amira@shop.tn']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@shop.tn', 'Amira', 'shop.tn'));

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.id', $user->id);
});

it('discards an unconfirmed sign-up when Google is authoritative', function () {
    $stranger = User::factory()->unverified()->create(['email' => 'amira@gmail.com', 'name' => 'Stranger']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Amira');

    expect($stranger->fresh())->toBeNull();
    expect(User::query()->sole()->password)->toBeNull();
});

it('keeps an unconfirmed sign-up when the terms are still missing', function () {
    $stranger = User::factory()->unverified()->create(['email' => 'amira@gmail.com']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'auth.terms_required');

    expect($stranger->fresh())->not->toBeNull();
});

it('refreshes the saved name and keeps the saved email', function () {
    $user = User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-1', 'name' => 'Old name']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira.new@gmail.com', 'New name'));

    $this->postJson('/api/v1/auth/google', googleSignInBody(['terms_version' => null]))
        ->assertOk()
        ->assertJsonPath('data.account.name', 'New name')
        ->assertJsonPath('data.account.email', 'amira@gmail.com');

    expect($user->fresh()->email)->toBe('amira@gmail.com');
});

it('keeps the saved name when Google sends none', function () {
    User::factory()->create(['email' => 'amira@gmail.com', 'google_id' => 'sub-1', 'name' => 'Amira']);
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', null));

    $this->postJson('/api/v1/auth/google', googleSignInBody())
        ->assertOk()
        ->assertJsonPath('data.account.name', 'Amira');
});

it('ends two concurrent first sign-ins on one account', function () {
    fakeGoogleIdentity(new GoogleIdentity('sub-1', 'amira@gmail.com', 'Amira'));
    $otherRequestWinsTheRace = function () {
        DB::table('users')->insert(User::factory()->raw([
            'email' => 'amira@gmail.com',
            'google_id' => 'sub-1',
            'password' => null,
        ]));
    };
    $raceHasHappened = false;
    User::creating(function () use (&$raceHasHappened, $otherRequestWinsTheRace) {
        if (! $raceHasHappened) {
            $raceHasHappened = true;
            $otherRequestWinsTheRace();
        }
    });

    $response = $this->postJson('/api/v1/auth/google', googleSignInBody());

    $response->assertOk()->assertJsonPath('data.status', 'signed_in');
    expect($raceHasHappened)->toBeTrue();
    expect(User::query()->count())->toBe(1);
    expect($response->json('data.account.id'))->toBe(User::query()->sole()->id);
});

it('accepts the test identity only in test mode', function () {
    config(['shop_keeper.auth_test_mode' => true]);

    $this->postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertOk()
        ->assertJsonPath('data.account.email', 'amira@example.com');
});

it('refuses the test identity with test mode off', function () {
    $this->postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);
});

it('refuses the test identity in production, even if configured', function () {
    config(['shop_keeper.auth_test_mode' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->postJson('/api/v1/auth/google', googleSignInBody(['id_token' => 'test:amira@example.com']))
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.google_invalid']]);

    expect(User::query()->count())->toBe(0);
});
