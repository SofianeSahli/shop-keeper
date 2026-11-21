<?php

use App\Modules\Shops\Controllers\Middleware\RequireShop;
use App\Modules\Transactions\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', RequireShop::ALIAS])->group(function () {
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store']);
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('transactions/{transaction}/cancellation', [TransactionController::class, 'cancel']);
});
