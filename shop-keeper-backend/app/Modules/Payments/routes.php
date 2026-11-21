<?php

use App\Modules\Payments\Controllers\PaymentController;
use App\Modules\Shops\Controllers\Middleware\RequireShop;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::post('payments/{payment}/cancellation', [PaymentController::class, 'cancel']);
});
