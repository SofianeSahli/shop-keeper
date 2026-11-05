<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class IssueDeviceSession
{
    public function handle(User $user, string $deviceId): string
    {
        $tokenName = strtolower($deviceId);

        return DB::transaction(function () use ($user, $tokenName): string {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $user->tokens()->where('name', $tokenName)->delete();

            return $user->createToken($tokenName)->plainTextToken;
        });
    }
}
