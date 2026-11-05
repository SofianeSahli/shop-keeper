<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;

final class SignOutDevice
{
    public function handle(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
