<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class ResetPassword
{
    public function __construct(private readonly ConsumeEmailCode $consumeEmailCode) {}

    public function handle(string $email, string $code, string $password): void
    {
        $user = User::query()->where('email', $email)->first();

        $this->consumeEmailCode->handle($user, EmailCodePurpose::PasswordReset, $code);

        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->password_failed_attempts = 0;
            $user->password_locked_until = null;
            $user->save();

            $user->tokens()->delete();
            PendingSignIn::query()->where('user_id', $user->id)->delete();
        });
    }
}
