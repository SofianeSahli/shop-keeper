<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ConsumeEmailCode
{
    public function handle(?User $user, EmailCodePurpose $purpose, string $code): void
    {
        if ($user === null) {
            Hash::check($code, CheckPassword::DUMMY_HASH);

            throw $this->refusal();
        }

        $isRight = DB::transaction(fn (): bool => $this->checkAndSpend($user, $purpose, $code));

        if (! $isRight) {
            throw $this->refusal();
        }
    }

    private function checkAndSpend(User $user, EmailCodePurpose $purpose, string $code): bool
    {
        $emailCode = EmailCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->lockForUpdate()
            ->first();

        if ($emailCode === null) {
            return false;
        }

        if ($emailCode->expires_at->isPast()) {
            $emailCode->delete();

            return false;
        }

        if (Hash::check($code, $emailCode->code_hash)) {
            $emailCode->delete();

            return true;
        }

        $this->countWrongTry($emailCode);

        return false;
    }

    private function countWrongTry(EmailCode $emailCode): void
    {
        $emailCode->failed_attempts++;

        if ($emailCode->failed_attempts >= EmailCode::MAX_FAILED_ATTEMPTS) {
            $emailCode->delete();

            return;
        }

        $emailCode->save();
    }

    private function refusal(): ApiException
    {
        return new ApiException('code.invalid', 422);
    }
}
