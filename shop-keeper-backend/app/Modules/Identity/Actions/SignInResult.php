<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

final readonly class SignInResult
{
    private function __construct(
        public ?User $user = null,
        public ?string $token = null,
        public ?string $pendingSignIn = null,
        public ?int $expiresInSeconds = null,
    ) {}

    public static function signedIn(User $user, string $token): self
    {
        return new self(user: $user, token: $token);
    }

    public static function codeRequired(string $pendingSignIn, int $expiresInSeconds): self
    {
        return new self(pendingSignIn: $pendingSignIn, expiresInSeconds: $expiresInSeconds);
    }
}
