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

        $typedEmail = Str::after($idToken, self::TEST_TOKEN_PREFIX);
        $email = mb_strtolower($typedEmail);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException('auth.google_invalid', 401);
        }

        $emailHash = hash('sha256', $email);
        $name = Str::before($email, '@');

        return new GoogleIdentity(
            sub: self::TEST_TOKEN_PREFIX.$emailHash,
            email: $email,
            name: $name,
        );
    }
}
