<?php

use App\Modules\Identity\Google\GoogleIdentityVerifier;
use App\Shared\Errors\ApiException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

const GOOGLE_KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
const TEST_KEY_ID = 'test-key';

function generateRsaKeyPair(): array
{
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($privateKey, $privatePem);

    return [$privatePem, openssl_pkey_get_details($privateKey)['rsa']];
}

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function googleKeySet(array $rsaKey): array
{
    return ['keys' => [[
        'kty' => 'RSA',
        'alg' => 'RS256',
        'use' => 'sig',
        'kid' => TEST_KEY_ID,
        'n' => base64UrlEncode($rsaKey['n']),
        'e' => base64UrlEncode($rsaKey['e']),
    ]]];
}

function googleClaims(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://accounts.google.com',
        'aud' => 'shop-keeper-test-client',
        'sub' => '1234567890',
        'email' => 'Amira@Gmail.com',
        'email_verified' => true,
        'name' => 'Amira',
        'iat' => time(),
        'exp' => time() + 3600,
    ], $overrides);
}

beforeEach(function () {
    [$this->privatePem, $this->rsaKey] = generateRsaKeyPair();

    $this->googleIsDown = false;

    Http::fake([GOOGLE_KEYS_URL => fn () => $this->googleIsDown
        ? Http::response('', 500)
        : Http::response(googleKeySet($this->rsaKey), 200, ['Cache-Control' => 'public, max-age=3600'])]);

    $this->signedToken = fn (array $overrides = []): string => JWT::encode(googleClaims($overrides), $this->privatePem, 'RS256', TEST_KEY_ID);
});

function expectGoogleRefusal(callable $verification, string $code): void
{
    try {
        $verification();
    } catch (ApiException $exception) {
        expect($exception->errorCode)->toBe($code);

        return;
    }

    test()->fail('The identity was accepted.');
}

it('accepts a valid identity and lowercases its email', function () {
    $identity = app(GoogleIdentityVerifier::class)->verify(($this->signedToken)());

    expect($identity->sub)->toBe('1234567890');
    expect($identity->email)->toBe('amira@gmail.com');
    expect($identity->name)->toBe('Amira');
    expect($identity->isAuthoritativeForEmail())->toBeTrue();
});

it('accepts either Google issuer', function () {
    $identity = app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['iss' => 'accounts.google.com']));

    expect($identity->sub)->toBe('1234567890');
});

it('accepts several client ids', function () {
    config(['shop_keeper.google_client_ids' => ['android-client', 'ios-client']]);

    $identity = app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['aud' => 'ios-client']));

    expect($identity->sub)->toBe('1234567890');
});

it('keeps the hosted domain and treats it as authoritative', function () {
    $identity = app(GoogleIdentityVerifier::class)->verify(($this->signedToken)([
        'email' => 'amira@shop.tn',
        'hd' => 'shop.tn',
    ]));

    expect($identity->isAuthoritativeForEmail())->toBeTrue();
});

it('is not authoritative for an address outside Gmail with no hosted domain', function () {
    $identity = app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['email' => 'amira@shop.tn']));

    expect($identity->isAuthoritativeForEmail())->toBeFalse();
});

it('refuses a token signed with another key', function () {
    [$otherPrivatePem] = generateRsaKeyPair();
    $forged = JWT::encode(googleClaims(), $otherPrivatePem, 'RS256', TEST_KEY_ID);

    expectGoogleRefusal(fn () => app(GoogleIdentityVerifier::class)->verify($forged), 'auth.google_invalid');
});

it('refuses a wrong issuer', function () {
    expectGoogleRefusal(
        fn () => app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['iss' => 'https://evil.example.com'])),
        'auth.google_invalid',
    );
});

it('refuses a token issued for another application', function () {
    expectGoogleRefusal(
        fn () => app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['aud' => 'another-application'])),
        'auth.google_invalid',
    );
});

it('refuses an expired token', function () {
    expectGoogleRefusal(
        fn () => app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['exp' => time() - 3600])),
        'auth.google_invalid',
    );
});

it('refuses an unverified email', function () {
    expectGoogleRefusal(
        fn () => app(GoogleIdentityVerifier::class)->verify(($this->signedToken)(['email_verified' => false])),
        'auth.google_invalid',
    );
});

it('refuses something that is not a token without calling Google', function () {
    expectGoogleRefusal(fn () => app(GoogleIdentityVerifier::class)->verify('not-a-token'), 'auth.google_invalid');

    Http::assertNothingSent();
});

it('caches the keys for the max-age Google sends', function () {
    $verifier = app(GoogleIdentityVerifier::class);

    $verifier->verify(($this->signedToken)());
    $verifier->verify(($this->signedToken)());

    Http::assertSentCount(1);

    $this->travel(3601)->seconds();
    $verifier->verify(($this->signedToken)());

    Http::assertSentCount(2);
});

it('answers auth.google_unavailable when the keys cannot be fetched', function () {
    $this->googleIsDown = true;
    $verifier = app(GoogleIdentityVerifier::class);

    try {
        $verifier->verify(($this->signedToken)());
        $this->fail('The identity was accepted.');
    } catch (ApiException $exception) {
        expect($exception->errorCode)->toBe('auth.google_unavailable');
        expect($exception->status)->toBe(503);
    }
});
