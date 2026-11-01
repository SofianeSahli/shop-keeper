<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;

final class FinishSignIn
{
    private const PENDING_TOKEN_BYTES = 40;

    public function __construct(private readonly IssueDeviceSession $issueDeviceSession) {}

    public function handle(User $user, string $deviceId): SignInResult
    {
        if ($user->hasPin()) {
            return $this->holdForPin($user, $deviceId);
        }

        return SignInResult::signedIn($user, $this->issueDeviceSession->handle($user, $deviceId));
    }

    private function holdForPin(User $user, string $deviceId): SignInResult
    {
        $pendingToken = bin2hex(random_bytes(self::PENDING_TOKEN_BYTES));

        PendingSignIn::query()->create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => PendingSignIn::hashToken($pendingToken),
            'expires_at' => now()->addSeconds(PendingSignIn::VALID_SECONDS),
        ]);

        return SignInResult::pinRequired($pendingToken, PendingSignIn::VALID_SECONDS);
    }
}
