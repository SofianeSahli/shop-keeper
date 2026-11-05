<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Closure;
use Illuminate\Support\Facades\DB;

final class RunCountedAttempt
{
    private const MAX_CONSECUTIVE_FAILURES = 5;

    private const LOCK_MINUTES = 15;

    public function handle(User $user, Closure $isRight): bool
    {
        return DB::transaction(function () use ($user, $isRight): bool {
            $lockedRow = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUntil = $lockedRow->password_locked_until;

            if ($lockedUntil?->isFuture()) {
                throw new ApiException('auth.password_locked', 429, retryAfterSeconds: (int) ceil(now()->diffInSeconds($lockedUntil)));
            }

            $passed = $isRight($lockedRow);

            $this->recordOutcome($lockedRow, $passed);

            return $passed;
        });
    }

    private function recordOutcome(User $lockedRow, bool $passed): void
    {
        $failures = $passed ? 0 : $lockedRow->password_failed_attempts + 1;
        $reachedLimit = $failures >= self::MAX_CONSECUTIVE_FAILURES;

        $lockedRow->password_failed_attempts = $reachedLimit ? 0 : $failures;
        $lockedRow->password_locked_until = $reachedLimit ? now()->addMinutes(self::LOCK_MINUTES) : null;
        $lockedRow->save();
    }
}
