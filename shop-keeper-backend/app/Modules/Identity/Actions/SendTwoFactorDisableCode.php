<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;

final class SendTwoFactorDisableCode
{
    public function __construct(private readonly SendEmailCode $sendEmailCode) {}

    public function handle(User $user): void
    {
        if (! $user->hasTwoFactor()) {
            throw new ApiException('two_factor.not_enabled', 409);
        }

        $this->sendEmailCode->handle($user, EmailCodePurpose::TwoFactorDisable);
    }
}
