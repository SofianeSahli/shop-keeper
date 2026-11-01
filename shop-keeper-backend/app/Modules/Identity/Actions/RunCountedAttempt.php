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

    public function handle(User $user, string $counterColumn, string $lockColumn, string $lockedCode, Closure $isRight): bool
    {
        return DB::transaction(function () use ($user, $counterColumn, $lockColumn, $lockedCode, $isRight): bool {
            $lockedRow = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUntil = $lockedRow->{$lockColumn};

            if ($lockedUntil?->isFuture()) {
                throw new ApiException($lockedCode, 429, retryAfterSeconds: (int) ceil(now()->diffInSeconds($lockedUntil)));
            }

            $passed = $isRight($lockedRow);

            $this->recordOutcome($lockedRow, $counterColumn, $lockColumn, $passed);

            return $passed;
        });
    }

    private function recordOutcome(User $lockedRow, string $counterColumn, string $lockColumn, bool $passed): void
    {
        $failures = $passed ? 0 : $lockedRow->{$counterColumn} + 1;
        $reachedLimit = $failures >= self::MAX_CONSECUTIVE_FAILURES;

        $lockedRow->{$counterColumn} = $reachedLimit ? 0 : $failures;
        $lockedRow->{$lockColumn} = $reachedLimit ? now()->addMinutes(self::LOCK_MINUTES) : null;
        $lockedRow->save();
    }
}
