<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Google\TestModeVerifier;
use App\Modules\Identity\Models\PendingSignIn;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    private const SIGN_IN_ATTEMPTS_PER_MINUTE = 30;

    private const EMAILS_PER_MINUTE_PER_ORIGIN = 10;

    private const EMAILS_PER_RECIPIENT = 3;

    private const EMAIL_RECIPIENT_WINDOW_MINUTES = 15;

    public function register(): void
    {
        $this->app->bind(GoogleIdentityChecker::class, TestModeVerifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'identity');

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');

        $this->defineRateLimiters();
    }

    private function defineRateLimiters(): void
    {
        RateLimiter::for('identity-sign-in', function (Request $request): Limit {
            $origin = $request->ip();

            return Limit::perMinute(self::SIGN_IN_ATTEMPTS_PER_MINUTE)->by('origin:'.$origin);
        });

        RateLimiter::for('identity-email-send', function (Request $request): array {
            $origin = $request->ip();
            $addressKey = $this->addressKey($request);
            $accountKey = $this->accountKey($request);
            $recipientKeys = array_filter([$addressKey, $accountKey]);

            return [
                Limit::perMinute(self::EMAILS_PER_MINUTE_PER_ORIGIN)->by('origin:'.$origin),
                ...array_map(
                    fn (string $key): Limit => Limit::perMinutes(self::EMAIL_RECIPIENT_WINDOW_MINUTES, self::EMAILS_PER_RECIPIENT)->by($key),
                    $recipientKeys,
                ),
            ];
        });
    }

    private function addressKey(Request $request): ?string
    {
        $email = $request->input('email');

        if (! is_string($email) || $email === '') {
            return null;
        }

        $trimmedEmail = trim($email);
        $normalizedEmail = mb_strtolower($trimmedEmail);

        return 'address:'.hash('sha256', $normalizedEmail);
    }

    private function accountKey(Request $request): ?string
    {
        $accountId = $request->user()?->id ?? $this->accountIdOfPendingSignIn($request);

        return $accountId === null ? null : 'account:'.$accountId;
    }

    private function accountIdOfPendingSignIn(Request $request): ?int
    {
        $pendingToken = $request->input('pending_sign_in');

        if (! is_string($pendingToken)) {
            return null;
        }

        $tokenHash = PendingSignIn::hashToken($pendingToken);

        return PendingSignIn::query()
            ->where('token_hash', $tokenHash)
            ->value('user_id');
    }
}
