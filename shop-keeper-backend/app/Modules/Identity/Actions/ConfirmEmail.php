<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;

final class ConfirmEmail
{
    public function __construct(
        private readonly ConsumeEmailCode $consumeEmailCode,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $email, string $code, string $deviceId): SignInResult
    {
        $user = User::query()->where('email', $email)->first();

        $this->consumeEmailCode->handle($user, EmailCodePurpose::EmailConfirmation, $code);

        $user->email_verified_at = now();
        $user->save();

        return $this->finishSignIn->handle($user, $deviceId);
    }
}
