<?php

use App\Modules\Identity\Controllers\AccountController;
use App\Modules\Identity\Controllers\EmailSignUpController;
use App\Modules\Identity\Controllers\GoogleSignInController;
use App\Modules\Identity\Controllers\LogoutController;
use App\Modules\Identity\Controllers\PasswordResetController;
use App\Modules\Identity\Controllers\PasswordSignInController;
use App\Modules\Identity\Controllers\PinController;
use App\Modules\Identity\Controllers\PinSignInController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:identity-sign-in')->group(function () {
    Route::post('auth/google', GoogleSignInController::class);
    Route::post('auth/email/confirm', [EmailSignUpController::class, 'confirm']);
    Route::post('auth/email/login', PasswordSignInController::class);
    Route::post('auth/password/reset', [PasswordResetController::class, 'reset']);
    Route::post('auth/pin', PinSignInController::class);
});

Route::middleware('throttle:identity-email-send')->group(function () {
    Route::post('auth/email/register', [EmailSignUpController::class, 'register']);
    Route::post('auth/email/resend', [EmailSignUpController::class, 'resend']);
    Route::post('auth/password/forgot', [PasswordResetController::class, 'forgot']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AccountController::class, 'show']);
    Route::post('auth/logout', LogoutController::class);
    Route::post('me/pin', [PinController::class, 'store']);
    Route::put('me/pin', [PinController::class, 'update']);
    Route::delete('me/pin', [PinController::class, 'destroy']);
    Route::post('me/pin/recovery', [PinController::class, 'recover']);
});
