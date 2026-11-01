<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\EmailCodePurpose;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class RegisterWithEmail
{
    public function __construct(private readonly SendEmailCode $sendEmailCode) {}

    public function handle(string $name, string $email, string $password, string $termsVersion, ?string $phone): void
    {
        $signUp = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'phone' => $phone,
            'terms_version' => $termsVersion,
            'terms_accepted_at' => now(),
        ];

        try {
            $user = $this->saveUnconfirmedAccount($signUp);
        } catch (UniqueConstraintViolationException) {
            $user = $this->saveUnconfirmedAccount($signUp);
        }

        $this->sendEmailCode->handle($user, EmailCodePurpose::EmailConfirmation);
    }

    private function saveUnconfirmedAccount(array $signUp): User
    {
        return DB::transaction(function () use ($signUp): User {
            $existing = User::query()->where('email', $signUp['email'])->lockForUpdate()->first();

            if ($existing === null) {
                return User::query()->create($signUp);
            }

            if ($existing->hasConfirmedEmail()) {
                throw new ApiException('email.taken', 409);
            }

            $existing->update($signUp);

            return $existing;
        });
    }
}
