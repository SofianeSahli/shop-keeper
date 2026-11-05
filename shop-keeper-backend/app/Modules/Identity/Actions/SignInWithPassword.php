<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\Hash;

final class SignInWithPassword
{
    public function __construct(
        private readonly CheckPassword $checkPassword,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $email, string $password, string $deviceId): SignInResult
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, CheckPassword::DUMMY_HASH);

            throw $this->invalidCredentials();
        }

        if (! $this->checkPassword->handle($user, $password)) {
            throw $this->invalidCredentials();
        }

        if (! $user->hasConfirmedEmail()) {
            throw new ApiException('auth.email_unconfirmed', 403);
        }

        return $this->finishSignIn->handle($user, $deviceId);
    }

    private function invalidCredentials(): ApiException
    {
        return new ApiException('auth.invalid_credentials', 401);
    }
}
