<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\AuthTestMode;
use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCode;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class SendEmailCode
{
    private const TEST_MODE_CODE = '000000';

    private const CODE_LENGTH = 6;

    private const LARGEST_CODE = 999999;

    public function handle(User $user, EmailCodePurpose $purpose): void
    {
        $code = $this->newCode();

        EmailCode::query()->upsert(
            [[
                'user_id' => $user->id,
                'purpose' => $purpose->value,
                'code_hash' => Hash::make($code),
                'failed_attempts' => 0,
                'expires_at' => now()->addMinutes(EmailCode::VALID_MINUTES),
            ]],
            ['user_id', 'purpose'],
            ['code_hash', 'failed_attempts', 'expires_at'],
        );

        Mail::to($user->email)->queue(new EmailCodeMail($code, $purpose));
    }

    private function newCode(): string
    {
        if (AuthTestMode::isOn()) {
            return self::TEST_MODE_CODE;
        }

        return str_pad((string) random_int(0, self::LARGEST_CODE), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
