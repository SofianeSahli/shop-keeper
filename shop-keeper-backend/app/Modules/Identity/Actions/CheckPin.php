<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\Hash;

final class CheckPin
{
    public function __construct(private readonly RunCountedAttempt $runCountedAttempt) {}

    public function handle(User $user, string $pin): void
    {
        $isRight = $this->runCountedAttempt->handle(
            $user,
            'pin_failed_attempts',
            'pin_locked_until',
            'pin.locked',
            fn (User $lockedRow): bool => $lockedRow->hasPin() && Hash::check($pin, $lockedRow->pin_hash),
        );

        if (! $isRight) {
            throw new ApiException('pin.invalid', 401);
        }
    }
}
