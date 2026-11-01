<?php

use App\Modules\Identity\Google\GoogleIdentity;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Mail\EmailCodeMail;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

const JOURNEY_NAME = 'Amira Ben Salah';
const JOURNEY_EMAIL = 'amira.journey@example.com';
const JOURNEY_PHONE = '+21620123456';
const JOURNEY_PASSWORD = 'journey secret password';
const JOURNEY_NEW_PASSWORD = 'journey new secret password';
const JOURNEY_PIN = '483920';
const JOURNEY_ID_TOKEN = 'the.raw.google-id-token';

function googleCheckerThrowing(Throwable $failure): GoogleIdentityChecker
{
    return new class($failure) implements GoogleIdentityChecker
    {
        public function __construct(private Throwable $failure) {}

        public function verify(string $idToken): GoogleIdentity
        {
            throw $this->failure;
        }
    };
}

function queryFailureHoldingPersonalData(): QueryException
{
    return new QueryException(
        'mysql',
        'insert into users (name, email, phone) values (?, ?, ?)',
        [JOURNEY_NAME, JOURNEY_EMAIL, JOURNEY_PHONE],
        new PDOException('SQLSTATE[HY000]: General error for '.JOURNEY_EMAIL),
    );
}

it('keeps personal data and secrets out of every log entry of a journey', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message.' '.json_encode($event->context, JSON_PARTIAL_OUTPUT_ON_ERROR);
    });
    Mail::fake();

    $this->postJson('/api/v1/auth/email/register', [
        'name' => JOURNEY_NAME,
        'email' => JOURNEY_EMAIL,
        'password' => JOURNEY_PASSWORD,
        'phone' => JOURNEY_PHONE,
        'terms_version' => config('shop_keeper.terms_version'),
    ])->assertStatus(202);
    $confirmationCode = Mail::queued(EmailCodeMail::class)->last()->code;

    $session = $this->postJson('/api/v1/auth/email/confirm', [
        'email' => JOURNEY_EMAIL,
        'code' => $confirmationCode,
        'device_id' => fakeDeviceId(),
    ])->assertOk()->json('data.token');

    $this->withToken($session)->postJson('/api/v1/me/pin', ['pin' => JOURNEY_PIN])->assertNoContent();

    $pendingToken = $this->postJson('/api/v1/auth/email/login', [
        'email' => JOURNEY_EMAIL,
        'password' => JOURNEY_PASSWORD,
        'device_id' => fakeDeviceId(),
    ])->assertOk()->json('data.pending_sign_in');

    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => '000111'])->assertStatus(401);
    $this->postJson('/api/v1/auth/pin', ['pending_sign_in' => $pendingToken, 'pin' => JOURNEY_PIN])->assertOk();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => JOURNEY_EMAIL])->assertStatus(202);
    $resetCode = Mail::queued(EmailCodeMail::class)->last()->code;
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => JOURNEY_EMAIL,
        'code' => $resetCode,
        'password' => JOURNEY_NEW_PASSWORD,
    ])->assertNoContent();

    app()->instance(GoogleIdentityChecker::class, googleCheckerThrowing(queryFailureHoldingPersonalData()));
    $this->postJson('/api/v1/auth/google', ['id_token' => JOURNEY_ID_TOKEN, 'device_id' => fakeDeviceId()])->assertStatus(500);

    app()->instance(GoogleIdentityChecker::class, googleCheckerThrowing(new RuntimeException('An unexpected failure')));
    $this->postJson('/api/v1/auth/google', ['id_token' => JOURNEY_ID_TOKEN, 'device_id' => fakeDeviceId()])->assertStatus(500);

    $log = implode("\n", $logged);
    $secrets = [
        JOURNEY_NAME, JOURNEY_EMAIL, JOURNEY_PHONE, JOURNEY_PASSWORD, JOURNEY_NEW_PASSWORD, JOURNEY_PIN,
        JOURNEY_ID_TOKEN, $confirmationCode, $resetCode, $session, $pendingToken,
    ];

    expect($logged)->toHaveCount(2);
    expect($log)->toContain('database.query_failed');

    foreach ($secrets as $secret) {
        expect($log)->not->toContain($secret);
    }
});
