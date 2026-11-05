<?php

namespace App\Modules\Identity\Actions;

use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;

final class ResendSignInCode
{
    public function __construct(
        private readonly LockLivePendingSignIn $lockLivePendingSignIn,
        private readonly SendSignInCode $sendSignInCode,
    ) {}

    public function handle(string $pendingToken): void
    {
        $wasResent = DB::transaction(function () use ($pendingToken): bool {
            $pendingSignIn = $this->lockLivePendingSignIn->handle($pendingToken);

            if ($pendingSignIn === null) {
                return false;
            }

            $this->sendSignInCode->handle($pendingSignIn->user, $pendingSignIn);

            return true;
        });

        if (! $wasResent) {
            throw new ApiException('auth.pending_invalid', 401);
        }
    }
}
