<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;

final class FinishSignIn
{
    private const PENDING_TOKEN_BYTES = 40;

    public function __construct(
        private readonly IssueDeviceSession $issueDeviceSession,
        private readonly SendSignInCode $sendSignInCode,
    ) {}

    public function handle(User $user, string $deviceId): SignInResult
    {
        if ($user->hasTwoFactor()) {
            return $this->holdForCode($user, $deviceId);
        }

        return SignInResult::signedIn($user, $this->issueDeviceSession->handle($user, $deviceId));
    }

    private function holdForCode(User $user, string $deviceId): SignInResult
    {
        $pendingToken = bin2hex(random_bytes(self::PENDING_TOKEN_BYTES));

        $this->sendSignInCode->handle($user, new PendingSignIn([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => PendingSignIn::hashToken($pendingToken),
        ]));

        return SignInResult::codeRequired($pendingToken, PendingSignIn::VALID_SECONDS);
    }
}
