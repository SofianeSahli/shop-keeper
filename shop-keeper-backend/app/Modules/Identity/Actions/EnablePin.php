<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class EnablePin
{
    public function handle(User $user, string $pin): void
    {
        DB::transaction(function () use ($user, $pin): void {
            $lockedRow = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedRow->hasPin()) {
                throw new ApiException('pin.already_enabled', 409);
            }

            $lockedRow->pin_hash = Hash::make($pin);
            $lockedRow->save();
        });
    }
}
