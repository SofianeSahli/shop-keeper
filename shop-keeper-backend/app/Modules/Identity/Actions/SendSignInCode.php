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

        $pendingSignIn->fill([
            'code_hash' => Hash::make($code),
            'failed_attempts' => 0,
            'expires_at' => now()->addSeconds(PendingSignIn::VALID_SECONDS),
        ])->save();

        Mail::to($user->email)->queue(new EmailCodeMail($code, EmailCodePurpose::SignIn));
    }
}
