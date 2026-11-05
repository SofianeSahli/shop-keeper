<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ConfirmSignInCode
{
    public function __construct(
        private readonly LockLivePendingSignIn $lockLivePendingSignIn,
        private readonly IssueDeviceSession $issueDeviceSession,
    ) {}

    public function handle(string $pendingToken, string $code): SignInResult
    {
        $outcome = DB::transaction(fn (): SignInResult|ApiException => $this->checkAndSpend($pendingToken, $code));

        if ($outcome instanceof ApiException) {
            throw $outcome;
        }

        return $outcome;
    }

    private function checkAndSpend(string $pendingToken, string $code): SignInResult|ApiException
    {
        $pendingSignIn = $this->lockLivePendingSignIn->handle($pendingToken);

        if ($pendingSignIn === null) {
            return $this->signInEnded();
        }

        if (! Hash::check($code, $pendingSignIn->code_hash)) {
            return $this->countWrongTry($pendingSignIn);
        }

        $pendingSignIn->delete();

        return SignInResult::signedIn(
            $pendingSignIn->user,
            $this->issueDeviceSession->handle($pendingSignIn->user, $pendingSignIn->device_id),
        );
    }

    private function countWrongTry(PendingSignIn $pendingSignIn): ApiException
    {
        $pendingSignIn->failed_attempts++;

        if ($pendingSignIn->failed_attempts >= PendingSignIn::MAX_FAILED_ATTEMPTS) {
            $pendingSignIn->delete();

            return $this->signInEnded();
        }

        $pendingSignIn->save();

        return new ApiException('code.invalid', 422);
    }

    private function signInEnded(): ApiException
    {
        return new ApiException('auth.pending_invalid', 401);
    }
}
