<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Console\ResetPinCommand;
use App\Modules\Identity\Google\GoogleIdentityChecker;
use App\Modules\Identity\Google\TestModeVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    private const SIGN_IN_ATTEMPTS_PER_MINUTE = 30;

    private const EMAILS_PER_MINUTE_PER_ORIGIN = 10;

    private const EMAILS_PER_ADDRESS = 3;

    private const EMAIL_ADDRESS_WINDOW_MINUTES = 15;

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

        if ($this->app->runningInConsole()) {
            $this->commands([ResetPinCommand::class]);
        }
    }

    private function defineRateLimiters(): void
    {
        RateLimiter::for('identity-sign-in', fn (Request $request): Limit => Limit::perMinute(self::SIGN_IN_ATTEMPTS_PER_MINUTE)
            ->by('origin:'.$request->ip()));

        RateLimiter::for('identity-email-send', function (Request $request): array {
            $limits = [Limit::perMinute(self::EMAILS_PER_MINUTE_PER_ORIGIN)->by('origin:'.$request->ip())];
            $email = $request->input('email');

            if (is_string($email) && $email !== '') {
                $limits[] = Limit::perMinutes(self::EMAIL_ADDRESS_WINDOW_MINUTES, self::EMAILS_PER_ADDRESS)
                    ->by('address:'.hash('sha256', mb_strtolower(trim($email))));
            }

            return $limits;
        });
    }
}
