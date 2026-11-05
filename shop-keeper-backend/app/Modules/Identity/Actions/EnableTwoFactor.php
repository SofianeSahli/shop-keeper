<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;

final class EnableTwoFactor
{
    public function handle(User $user): void
    {
        $turnedOn = User::query()
            ->whereKey($user->id)
            ->whereNull('two_factor_enabled_at')
            ->update(['two_factor_enabled_at' => now()]);

        if ($turnedOn === 0) {
            throw new ApiException('two_factor.already_enabled', 409);
        }
    }
}
