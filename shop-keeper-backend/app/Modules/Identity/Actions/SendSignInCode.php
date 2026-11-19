<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Mail\EmailCodeMail;
use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\PendingSignIn;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

final class SendSignInCode
{
    public function __construct(private readonly GenerateEmailCode $generateEmailCode) {}

    public function handle(User $user, PendingSignIn $pendingSignIn): void
    {
        $code = $this->generateEmailCode->handle();
        $codeHash = Hash::make($code);
        $expiresAt = now()->addSeconds(PendingSignIn::VALID_SECONDS);

        $pendingSignIn->fill([
            'code_hash' => $codeHash,
            'failed_attempts' => 0,
            'expires_at' => $expiresAt,
        ])->save();

        $mail = new EmailCodeMail($code, EmailCodePurpose::SignIn);
        Mail::to($user->email)->queue($mail);
    }
}
