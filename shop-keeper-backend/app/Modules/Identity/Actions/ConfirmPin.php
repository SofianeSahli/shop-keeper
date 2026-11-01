<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;

final class ConfirmPin
{
    public function __construct(
        private readonly CheckPin $checkPin,
        private readonly IssueDeviceSession $issueDeviceSession,
    ) {}

    public function handle(string $pendingToken, string $pin): SignInResult
    {
        $pendingSignIn = $this->findLivePendingSignIn($pendingToken);
        $user = $pendingSignIn->user;

        $this->checkPin->handle($user, $pin);

        return DB::transaction(function () use ($pendingSignIn, $user): SignInResult {
            if (PendingSignIn::query()->whereKey($pendingSignIn->id)->delete() === 0) {
                throw $this->refusal();
            }

            return SignInResult::signedIn($user, $this->issueDeviceSession->handle($user, $pendingSignIn->device_id));
        });
    }

    private function findLivePendingSignIn(string $pendingToken): PendingSignIn
    {
        $pendingSignIn = PendingSignIn::query()
            ->where('token_hash', PendingSignIn::hashToken($pendingToken))
            ->first();

        if ($pendingSignIn === null) {
            throw $this->refusal();
        }

        if ($pendingSignIn->expires_at->isPast()) {
            $pendingSignIn->delete();

            throw $this->refusal();
        }

        return $pendingSignIn;
    }

    private function refusal(): ApiException
    {
        return new ApiException('auth.pending_invalid', 401);
    }
}
