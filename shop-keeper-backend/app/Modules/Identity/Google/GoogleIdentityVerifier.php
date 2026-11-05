<?php

namespace App\Modules\Identity\Google;

use App\Shared\Errors\ApiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use stdClass;
use UnexpectedValueException;

final class GoogleIdentityVerifier implements GoogleIdentityChecker
{
    private const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const KEYS_CACHE_KEY = 'identity.google_signing_keys';

    private const KEYS_FETCH_TIMEOUT_SECONDS = 5;

    private const ALLOWED_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const SIGNING_ALGORITHM = 'RS256';

    private const CLOCK_SKEW_SECONDS = 60;

    private const JWT_SEGMENT_SEPARATORS = 2;

    private const NAME_MAX_LENGTH = 120;

    public function verify(string $idToken): GoogleIdentity
    {
        if (substr_count($idToken, '.') !== self::JWT_SEGMENT_SEPARATORS) {
            throw $this->refusal();
        }

        $claims = $this->decode($idToken);

        if (! $this->hasExpectedClaims($claims)) {
            throw $this->refusal();
        }

        return new GoogleIdentity(
            sub: $claims->sub,
            email: mb_strtolower($claims->email),
            name: $this->savableName($claims->name ?? null),
            hostedDomain: $claims->hd ?? null,
        );
    }

    private function decode(string $idToken): stdClass
    {
        $keys = $this->signingKeys();

        JWT::$leeway = self::CLOCK_SKEW_SECONDS;

        try {
            return JWT::decode($idToken, JWK::parseKeySet($keys, self::SIGNING_ALGORITHM));
        } catch (UnexpectedValueException|LogicException) {
            throw $this->refusal();
        }
    }

    private function hasExpectedClaims(stdClass $claims): bool
    {
        return in_array($claims->iss ?? null, self::ALLOWED_ISSUERS, true)
            && in_array($claims->aud ?? null, config('shop_keeper.google_client_ids'), true)
            && in_array($claims->email_verified ?? null, [true, 'true'], true)
            && is_string($claims->sub ?? null)
            && is_string($claims->email ?? null);
    }

    private function savableName(mixed $name): ?string
    {
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return mb_substr(trim($name), 0, self::NAME_MAX_LENGTH);
    }

    private function signingKeys(): array
    {
        return Cache::get(self::KEYS_CACHE_KEY) ?? $this->fetchSigningKeys();
    }

    private function fetchSigningKeys(): array
    {
        try {
            $response = Http::timeout(self::KEYS_FETCH_TIMEOUT_SECONDS)->get(self::KEYS_URL);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        $keys = $response->json();

        if (! $response->successful() || ! is_array($keys['keys'] ?? null)) {
            throw $this->unavailable();
        }

        $this->cacheForGooglesMaxAge($keys, $response);

        return $keys;
    }

    private function cacheForGooglesMaxAge(array $keys, Response $response): void
    {
        if (preg_match('/max-age=(\d+)/', (string) $response->header('Cache-Control'), $maxAge) === 1) {
            Cache::put(self::KEYS_CACHE_KEY, $keys, (int) $maxAge[1]);
        }
    }

    private function refusal(): ApiException
    {
        return new ApiException('auth.google_invalid', 401);
    }

    private function unavailable(): ApiException
    {
        return new ApiException('auth.google_unavailable', 503);
    }
}
