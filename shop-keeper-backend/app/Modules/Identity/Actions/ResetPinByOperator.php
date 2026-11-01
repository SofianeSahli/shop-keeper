<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class ResetPinByOperator
{
    public function handle(string $email): bool
    {
        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'pin_hash' => null,
                'pin_failed_attempts' => 0,
                'pin_locked_until' => null,
            ])->save();

            $user->tokens()->delete();
            PendingSignIn::query()->where('user_id', $user->id)->delete();
        });

        return true;
    }
}
