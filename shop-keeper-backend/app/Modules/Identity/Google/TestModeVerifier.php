<?php

namespace App\Modules\Identity\Google;

use App\Modules\Identity\AuthTestMode;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Str;

final class TestModeVerifier implements GoogleIdentityChecker
{
    private const TEST_TOKEN_PREFIX = 'test:';

    public function __construct(private readonly GoogleIdentityVerifier $googleIdentityVerifier) {}

    public function verify(string $idToken): GoogleIdentity
    {
        if (! AuthTestMode::isOn() || ! str_starts_with($idToken, self::TEST_TOKEN_PREFIX)) {
            return $this->googleIdentityVerifier->verify($idToken);
        }

        $email = mb_strtolower(Str::after($idToken, self::TEST_TOKEN_PREFIX));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException('auth.google_invalid', 401);
        }

        return new GoogleIdentity(
            sub: self::TEST_TOKEN_PREFIX.hash('sha256', $email),
            email: $email,
            name: Str::before($email, '@'),
        );
    }
}
