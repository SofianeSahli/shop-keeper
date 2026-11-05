<?php

use App\Modules\Identity\Controllers\AccountController;
use App\Modules\Identity\Controllers\EmailSignUpController;
use App\Modules\Identity\Controllers\GoogleSignInController;
use App\Modules\Identity\Controllers\LogoutController;
use App\Modules\Identity\Controllers\PasswordResetController;
use App\Modules\Identity\Controllers\PasswordSignInController;
use App\Modules\Identity\Controllers\TwoFactorController;
use App\Modules\Identity\Controllers\TwoFactorSignInController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:identity-sign-in')->group(function () {
    Route::post('auth/google', GoogleSignInController::class);
    Route::post('auth/email/confirm', [EmailSignUpController::class, 'confirm']);
    Route::post('auth/email/login', PasswordSignInController::class);
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);
    Route::post('auth/two-factor', [TwoFactorSignInController::class, 'confirm']);
});

Route::middleware('throttle:identity-email-send')->group(function () {
    Route::post('auth/email/register', [EmailSignUpController::class, 'register']);
    Route::post('auth/email/resend', [EmailSignUpController::class, 'resend']);
    Route::post('auth/password/forgot', [PasswordResetController::class, 'forgot']);
    Route::post('auth/two-factor/resend', [TwoFactorSignInController::class, 'resend']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AccountController::class, 'show']);
    Route::post('auth/logout', LogoutController::class);
    Route::post('me/two-factor', [TwoFactorController::class, 'store']);
    Route::post('me/two-factor/disable-code', [TwoFactorController::class, 'sendDisableCode'])
        ->middleware('throttle:identity-email-send');
    Route::delete('me/two-factor', [TwoFactorController::class, 'destroy']);
});
