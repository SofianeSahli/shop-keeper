<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RecoverPin
{
    public function __construct(
        private readonly GoogleIdentityChecker $googleIdentityChecker,
        private readonly CheckPassword $checkPassword,
    ) {}

    public function handle(User $user, int $currentTokenId, string $newPin, ?string $idToken, ?string $password): void
    {
        if ($idToken !== null) {
            $this->proveWithGoogle($user, $idToken);
        } else {
            $this->proveWithPassword($user, $password);
        }

        DB::transaction(function () use ($user, $currentTokenId, $newPin): void {
            $user->forceFill([
                'pin_hash' => Hash::make($newPin),
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ])->save();

            $user->tokens()->whereKeyNot($currentTokenId)->delete();
            PendingSignIn::query()->where('user_id', $user->id)->delete();
        });
    }

    private function proveWithGoogle(User $user, string $idToken): void
    {
        if ($this->googleIdentityChecker->verify($idToken)->sub !== $user->google_id) {
            throw new ApiException('auth.google_invalid', 401);
        }
    }

    private function proveWithPassword(User $user, string $password): void
    {
        if (! $this->checkPassword->handle($user, $password)) {
            throw new ApiException('auth.invalid_credentials', 401);
        }
    }
}
