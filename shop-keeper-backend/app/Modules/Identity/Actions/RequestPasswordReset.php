<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;

final class RequestPasswordReset
{
    public function __construct(
        private readonly SendEmailCode $sendEmailCode,
        private readonly SpendTimeOfSendingCode $spendTimeOfSendingCode,
    ) {}

    public function handle(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->hasConfirmedEmail()) {
            $this->spendTimeOfSendingCode->handle();

            return;
        }

        $this->sendEmailCode->handle($user, EmailCodePurpose::PasswordReset);
    }
}
