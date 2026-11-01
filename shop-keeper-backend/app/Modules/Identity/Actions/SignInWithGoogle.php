<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Google\GoogleIdentity;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Models\User;
use App\Shared\Errors\ApiException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class SignInWithGoogle
{
    public function __construct(
        private readonly GoogleIdentityChecker $googleIdentityChecker,
        private readonly FinishSignIn $finishSignIn,
    ) {}

    public function handle(string $idToken, string $deviceId, ?string $termsVersion, ?string $phone): SignInResult
    {
        $identity = $this->googleIdentityChecker->verify($idToken);

        try {
            $user = $this->resolveAccount($identity, $termsVersion, $phone);
        } catch (UniqueConstraintViolationException) {
            $user = $this->resolveAccount($identity, $termsVersion, $phone);
        }

        return $this->finishSignIn->handle($user, $deviceId);
    }

    private function resolveAccount(GoogleIdentity $identity, ?string $termsVersion, ?string $phone): User
    {
        $linkedAccount = User::query()->where('google_id', $identity->sub)->first();

        if ($linkedAccount !== null) {
            return $this->refreshName($linkedAccount, $identity);
        }

        if (User::query()->where('email', $identity->email)->doesntExist()) {
            return $this->createAccount($identity, $termsVersion, $phone);
        }

        return DB::transaction(fn (): User => $this->claimEmail($identity, $termsVersion, $phone));
    }

    private function claimEmail(GoogleIdentity $identity, ?string $termsVersion, ?string $phone): User
    {
        $match = User::query()->where('email', $identity->email)->lockForUpdate()->firstOrFail();

        if ($match->google_id !== null || ! $identity->isAuthoritativeForEmail()) {
            throw new ApiException('auth.google_email_conflict', 409);
        }

        if ($match->hasConfirmedEmail()) {
            $match->google_id = $identity->sub;

            return $this->refreshName($match, $identity);
        }

        $match->delete();

        return $this->createAccount($identity, $termsVersion, $phone);
    }

    private function createAccount(GoogleIdentity $identity, ?string $termsVersion, ?string $phone): User
    {
        if ($termsVersion === null) {
            throw new ApiException('auth.terms_required', 422);
        }

        return User::query()->create([
            'name' => $identity->nameForNewAccount(),
            'email' => $identity->email,
            'email_verified_at' => now(),
            'google_id' => $identity->sub,
            'phone' => $phone,
            'terms_version' => $termsVersion,
            'terms_accepted_at' => now(),
        ]);
    }

    private function refreshName(User $user, GoogleIdentity $identity): User
    {
        $user->name = $identity->name ?? $user->name;
        $user->save();

        return $user;
    }
}
