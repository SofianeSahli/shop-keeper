<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;

final class CheckPassword
{
    public const DUMMY_HASH = '$2y$12$h0p5qCaxCUZxstJN4el8OOGdMis2f73qlhqTx1ElnnqBgUOAAr7ou';

    public function __construct(private readonly RunCountedAttempt $runCountedAttempt) {}

    public function handle(User $user, string $password): bool
    {
        return $this->runCountedAttempt->handle(
            $user,
            'password_failed_attempts',
            'password_locked_until',
            'auth.password_locked',
            function (User $lockedRow) use ($password): bool {
                $matchesStoredOrDummy = Hash::check($password, $lockedRow->password ?? self::DUMMY_HASH);

                return $lockedRow->password !== null && $matchesStoredOrDummy;
            },
        );
    }
}
