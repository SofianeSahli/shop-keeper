<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;

final class DisableTwoFactor
{
    public function __construct(private readonly ConsumeEmailCode $consumeEmailCode) {}

    public function handle(User $user, string $code): void
    {
        if (! $user->hasTwoFactor()) {
            throw new ApiException('two_factor.not_enabled', 409);
        }

        $this->consumeEmailCode->handle($user, EmailCodePurpose::TwoFactorDisable, $code);

        $user->two_factor_enabled_at = null;
        $user->save();
    }
}
