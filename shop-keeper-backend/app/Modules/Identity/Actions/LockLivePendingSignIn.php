<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;

final class LockLivePendingSignIn
{
    public function handle(string $pendingToken): ?PendingSignIn
    {
        $pendingSignIn = PendingSignIn::query()
            ->where('token_hash', PendingSignIn::hashToken($pendingToken))
            ->lockForUpdate()
            ->first();

        if ($pendingSignIn?->expires_at->isPast()) {
            $pendingSignIn->delete();

            return null;
        }

        return $pendingSignIn;
    }
}
